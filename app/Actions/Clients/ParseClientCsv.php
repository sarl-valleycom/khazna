<?php

namespace App\Actions\Clients;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ParseClientCsv
{
    /**
     * @return array{
     *     rows: list<array{line: int, status: string, name: string|null, phone_display: string|null, manager_email: string|null, reason: string|null}>,
     *     valid_rows: list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>,
     *     total_count: int,
     *     valid_count: int,
     *     invalid_count: int,
     *     duplicate_count: int
     * }
     */
    public function handle(string $contents, User $actor): array
    {
        $contents = $this->utf8($contents);
        $delimiter = $this->delimiter($contents);

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException(__('The file could not be read.'));
        }

        fwrite($stream, $contents);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '\\');

        if ($header === false) {
            fclose($stream);

            throw new InvalidArgumentException(__('The file has no header row.'));
        }

        $columns = $this->columns($header);
        $missing = array_diff(['name', 'phone', 'account_manager_email'], array_keys($columns));

        if ($missing !== []) {
            fclose($stream);

            throw new InvalidArgumentException(__('The CSV is missing the required column: :columns.', [
                'columns' => implode(', ', $missing),
            ]));
        }

        $managers = $this->managers($actor);
        $rows = [];
        $validData = [];
        $seenPhones = [];
        $line = 1;

        while (($record = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            if ($record === [null] || $this->isEmpty($record)) {
                continue;
            }

            $record = array_pad($record, count($header), '');
            $name = $this->field($record, $columns, 'name');
            $contact = $this->field($record, $columns, 'contact');
            $phone = $this->field($record, $columns, 'phone');
            $email = $this->field($record, $columns, 'email');
            $address = $this->field($record, $columns, 'address');
            $notes = $this->field($record, $columns, 'notes');
            $managerEmail = strtolower($this->field($record, $columns, 'account_manager_email'));
            $errors = [];

            if ($name === '') {
                $errors[] = __('Name is required.');
            } elseif (mb_strlen($name) > 255) {
                $errors[] = __('Name must be 255 characters or less.');
            }

            if ($contact !== '' && mb_strlen($contact) > 255) {
                $errors[] = __('Contact must be 255 characters or less.');
            }

            $normalizedPhone = Phone::normalize($phone);

            if ($phone === '') {
                $errors[] = __('Phone is required.');
            } elseif ($normalizedPhone === null) {
                $errors[] = __('Enter a valid Algerian mobile number, for example 0550 12 34 56.');
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = __('Enter a valid email address.');
            }

            if (mb_strlen($address) > 1000) {
                $errors[] = __('Address must be 1000 characters or less.');
            }

            if (mb_strlen($notes) > 5000) {
                $errors[] = __('Notes must be 5000 characters or less.');
            }

            $manager = $managerEmail === '' ? null : ($managers[$managerEmail] ?? null);

            if ($managerEmail === '') {
                $errors[] = __('Account manager email is required.');
            } elseif (filter_var($managerEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = __('Enter a valid account manager email.');
            } elseif ($manager === null) {
                $errors[] = __('No active account manager with this email.');
            }

            $status = $errors === [] ? 'valid' : 'invalid';
            $reason = $errors === [] ? null : implode(' ', $errors);

            if ($status === 'valid' && $normalizedPhone !== null && $manager !== null) {
                if (isset($seenPhones[$normalizedPhone])) {
                    $status = 'duplicate';
                    $reason = (string) __('Same phone as line :line.', ['line' => $seenPhones[$normalizedPhone]]);
                } else {
                    $seenPhones[$normalizedPhone] = $line;
                    $validData[$line] = [
                        'name' => $name,
                        'contact' => $contact === '' ? null : $contact,
                        'phone' => $normalizedPhone,
                        'email' => $email === '' ? null : $email,
                        'address' => $address === '' ? null : $address,
                        'notes' => $notes === '' ? null : $notes,
                        'account_manager_id' => $manager->id,
                        'office_id' => $actor->office_id ?? $manager->office_id,
                    ];
                }
            }

            $rows[] = [
                'line' => $line,
                'status' => $status,
                'name' => $name === '' ? null : $name,
                'phone_display' => Phone::formatLocal($phone),
                'manager_email' => $managerEmail === '' ? null : $managerEmail,
                'reason' => $reason,
            ];
        }

        fclose($stream);

        if ($rows === []) {
            throw new InvalidArgumentException(__('The file has no data rows.'));
        }

        $this->flagExistingPhones($rows, $validData, $seenPhones);

        $validRows = [];

        foreach ($rows as $row) {
            if ($row['status'] !== 'valid') {
                continue;
            }

            $data = $validData[$row['line']] ?? null;

            if ($data !== null) {
                $validRows[] = $data;
            }
        }

        $counts = ['valid' => 0, 'invalid' => 0, 'duplicate' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        return [
            'rows' => $rows,
            'valid_rows' => $validRows,
            'total_count' => count($rows),
            'valid_count' => $counts['valid'],
            'invalid_count' => $counts['invalid'],
            'duplicate_count' => $counts['duplicate'],
        ];
    }

    private function utf8(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            throw new InvalidArgumentException(__('The file must be UTF-8. In Excel, choose Save As → CSV UTF-8 (Comma delimited).'));
        }

        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw new InvalidArgumentException(__('The file must be UTF-8. In Excel, choose Save As → CSV UTF-8 (Comma delimited).'));
        }

        if (trim($contents) === '') {
            throw new InvalidArgumentException(__('The file is empty.'));
        }

        return $contents;
    }

    private function delimiter(string $contents): string
    {
        $firstLine = preg_split('/\r\n|\n|\r/', $contents)[0] ?? '';
        $delimiters = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delimiters);

        $delimiter = array_key_first($delimiters);

        if ($delimiters[$delimiter] === 0) {
            throw new InvalidArgumentException(__('The file has no header delimiter. Use a CSV file with commas.'));
        }

        return $delimiter;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>
     */
    private function columns(array $header): array
    {
        $aliases = [
            'client' => 'name',
            'client_name' => 'name',
            'phone_number' => 'phone',
            'mobile' => 'phone',
            'contact_person' => 'contact',
            'account_manager' => 'account_manager_email',
            'account_manager_e_mail' => 'account_manager_email',
            'manager_email' => 'account_manager_email',
        ];

        $columns = [];

        foreach ($header as $index => $value) {
            $key = strtolower(trim((string) $value));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
            $key = trim($key, '_');

            if ($key === '') {
                continue;
            }

            $columns[$aliases[$key] ?? $key] = $index;
        }

        return $columns;
    }

    /**
     * @param  array<int, string|null>  $record
     * @param  array<string, int>  $columns
     */
    private function field(array $record, array $columns, string $key): string
    {
        $index = $columns[$key] ?? null;

        if ($index === null) {
            return '';
        }

        return trim((string) ($record[$index] ?? ''));
    }

    /**
     * @param  array<int, string|null>  $record
     */
    private function isEmpty(array $record): bool
    {
        foreach ($record as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, User>
     */
    private function managers(User $actor): array
    {
        $managers = User::query()
            ->where('role', Role::AccountManager->value)
            ->where('active', true)
            ->when(
                $actor->office_id !== null,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query->whereNull('office_id')->orWhere('office_id', $actor->office_id),
                ),
            )
            ->get(['id', 'name', 'email', 'office_id']);

        $map = [];

        foreach ($managers as $manager) {
            $map[strtolower($manager->email)] = $manager;
        }

        return $map;
    }

    /**
     * @param  list<array{line: int, status: string, name: string|null, phone_display: string|null, manager_email: string|null, reason: string|null}>  $rows
     * @param  array<int, array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>  $validData
     * @param  array<string, int>  $seenPhones
     */
    private function flagExistingPhones(array &$rows, array &$validData, array $seenPhones): void
    {
        if ($seenPhones === []) {
            return;
        }

        $existing = [];

        foreach (array_chunk(array_keys($seenPhones), 500) as $phones) {
            $clients = Client::withTrashed()
                ->whereIn('phone', $phones)
                ->get(['name', 'phone']);

            foreach ($clients as $client) {
                $existing[$client->phone] ??= $client->name;
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['status'] !== 'valid') {
                continue;
            }

            $line = $row['line'];
            $data = $validData[$line] ?? null;

            if ($data === null || ! isset($existing[$data['phone']])) {
                continue;
            }

            $rows[$index]['status'] = 'duplicate';
            $rows[$index]['reason'] = (string) __('A client with this phone already exists: :name.', ['name' => $existing[$data['phone']]]);
            unset($validData[$line]);
        }
    }
}
