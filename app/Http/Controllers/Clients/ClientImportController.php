<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Clients\ImportClients;
use App\Actions\Clients\ParseClientCsv;
use App\Data\ClientImportPreviewData;
use App\Data\ClientImportRowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientImportRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class ClientImportController extends Controller
{
    private const CACHE_PREFIX = 'client-import:';

    private const TTL_MINUTES = 30;

    public function create(): InertiaResponse
    {
        $this->authorize('import', Client::class);

        return Inertia::render('clients/Import', [
            'preview' => null,
        ]);
    }

    public function preview(ClientImportRequest $request, ParseClientCsv $parser): RedirectResponse
    {
        $this->authorize('import', Client::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $file = $request->file('file');
        $path = $file?->getRealPath();
        $contents = $path === false || $path === null ? false : file_get_contents($path);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'file' => __('The file could not be read.'),
            ]);
        }

        try {
            $result = $parser->handle($contents, $actor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $token = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$token, [
            ...$result,
            'user_id' => $actor->id,
            'file_name' => basename($file->getClientOriginalName()),
        ], now()->addMinutes(self::TTL_MINUTES));

        return to_route('clients.import.show', ['token' => $token], 303);
    }

    public function showPreview(Request $request, string $token): InertiaResponse|RedirectResponse
    {
        $this->authorize('import', Client::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $payload = $this->payload($token);

        if ($payload === null) {
            return to_route('clients.import', [], 303)
                ->with('error', __('This import preview expired. Upload the file again.'));
        }

        if ($payload['user_id'] !== $actor->id) {
            abort(404);
        }

        return Inertia::render('clients/Import', [
            'preview' => $this->previewData($token, $payload),
        ]);
    }

    public function confirm(Request $request, ImportClients $importer): RedirectResponse
    {
        $this->authorize('import', Client::class);

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:40'],
        ]);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $payload = $this->payload($validated['token']);

        if ($payload === null) {
            return to_route('clients.import', [], 303)
                ->with('error', __('This import preview expired. Upload the file again.'));
        }

        if ($payload['user_id'] !== $actor->id) {
            abort(404);
        }

        if ($payload['valid_rows'] === []) {
            return to_route('clients.import.show', ['token' => $validated['token']], 303)
                ->with('error', __('There are no valid rows to import.'));
        }

        $count = $importer->handle($payload['valid_rows']);

        Cache::forget(self::CACHE_PREFIX.$validated['token']);

        $message = $count === 1
            ? __('Imported 1 client.')
            : __('Imported :count clients.', ['count' => $count]);

        return to_route('clients.index', [], 303)->with('success', $message);
    }

    public function template(): Response
    {
        $this->authorize('import', Client::class);

        $content = "\xEF\xBB\xBF".implode(',', [
            'name',
            'contact',
            'phone',
            'email',
            'address',
            'notes',
            'account_manager_email',
        ])."\n".implode(',', [
            'Example company',
            'Example contact',
            '0550 12 34 56',
            'contact@example.com',
            '1 Example street, Algiers',
            '',
            'manager@example.com',
        ])."\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clients-template.csv"',
        ]);
    }

    /**
     * @return array{
     *     rows: list<array{line: int, status: string, name: string|null, phone_display: string|null, manager_email: string|null, reason: string|null}>,
     *     valid_rows: list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>,
     *     total_count: int,
     *     valid_count: int,
     *     invalid_count: int,
     *     duplicate_count: int,
     *     user_id: int,
     *     file_name: string
     * }|null
     */
    private function payload(string $token): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($payload)) {
            return null;
        }

        /** @var array{rows: list<array{line: int, status: string, name: string|null, phone_display: string|null, manager_email: string|null, reason: string|null}>, valid_rows: list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>, total_count: int, valid_count: int, invalid_count: int, duplicate_count: int, user_id: int, file_name: string} $payload */
        return $payload;
    }

    /**
     * @param  array{
     *     rows: list<array{line: int, status: string, name: string|null, phone_display: string|null, manager_email: string|null, reason: string|null}>,
     *     valid_rows: list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>,
     *     total_count: int,
     *     valid_count: int,
     *     invalid_count: int,
     *     duplicate_count: int,
     *     user_id: int,
     *     file_name: string
     * }  $payload
     */
    private function previewData(string $token, array $payload): ClientImportPreviewData
    {
        $rows = array_map(fn (array $row): ClientImportRowData => new ClientImportRowData(
            line: $row['line'],
            status: $row['status'],
            name: $row['name'],
            phoneDisplay: $row['phone_display'],
            managerEmail: $row['manager_email'],
            reason: $row['reason'],
        ), $payload['rows']);

        return new ClientImportPreviewData(
            token: $token,
            fileName: $payload['file_name'],
            totalCount: $payload['total_count'],
            validCount: $payload['valid_count'],
            invalidCount: $payload['invalid_count'],
            duplicateCount: $payload['duplicate_count'],
            rows: $rows,
        );
    }
}
