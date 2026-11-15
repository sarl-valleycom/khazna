import { toast } from 'vue-sonner';

type Fetched = { blob: Blob; filename: string };

/** What to tell the person when the server did not hand over a file. */
async function messageFor(response: Response): Promise<string> {
    const type = response.headers.get('content-type') ?? '';

    if (type.includes('json')) {
        try {
            const data = (await response.json()) as { message?: string };

            if (data.message) {
                return data.message;
            }
        } catch {
            // Fall through to the generic message for the status.
        }
    }

    switch (response.status) {
        case 401:
            return 'Your session has ended. Log in again.';
        case 403:
            return 'You are not allowed to open this file.';
        case 404:
            return 'There is nothing to export here.';
        case 422:
            return 'The file could not be made for this choice. Change the period or filters and try again.';
        default:
            return 'The file could not be made. Try again in a moment.';
    }
}

function filenameOf(response: Response): string {
    const header = response.headers.get('content-disposition') ?? '';
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);

    return match?.[1] ? decodeURIComponent(match[1]) : 'khazna-export';
}

/**
 * Ask for a file. A refusal or a failure (403, 404, 422, 500, an ended session)
 * is shown as a message and returns null, so it is never saved as a file that
 * will not open.
 */
async function fetchFile(url: string): Promise<Fetched | null> {
    let response: Response;

    try {
        response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {
        toast.error(
            'Could not reach the server. Check your connection and try again.',
        );

        return null;
    }

    // A login page that came back with a 200 is not the file either.
    if (
        !response.ok ||
        (response.headers.get('content-type') ?? '').includes('text/html')
    ) {
        toast.error(await messageFor(response));

        return null;
    }

    return { blob: await response.blob(), filename: filenameOf(response) };
}

function save(file: Fetched): void {
    const href = URL.createObjectURL(file.blob);
    const link = document.createElement('a');

    link.href = href;
    link.download = file.filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(href), 60_000);
}

/** Download a file (Excel). Resolves once it was saved or a message was shown. */
export async function downloadFile(url: string): Promise<void> {
    const file = await fetchFile(url);

    if (file) {
        save(file);
    }
}

/**
 * Open a PDF in a new tab. The tab is opened first, inside the click, so a
 * browser does not treat it as a blocked pop-up; it is closed again if the file
 * could not be made.
 */
export async function openPdf(url: string): Promise<void> {
    const tab = window.open('', '_blank');
    const file = await fetchFile(url);

    if (!file) {
        tab?.close();

        return;
    }

    if (!tab) {
        save(file);

        return;
    }

    const href = URL.createObjectURL(file.blob);

    tab.location.href = href;
    setTimeout(() => URL.revokeObjectURL(href), 60_000);
}
