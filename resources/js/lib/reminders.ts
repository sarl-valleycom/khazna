import { toast } from 'vue-sonner';
import { postJson } from '@/lib/http';

/**
 * Ask the server to build and log the WhatsApp reminder for a due, then open
 * the wa.me link. The window is opened first, inside the click, so mobile
 * browsers do not treat it as a blocked pop-up.
 */
export async function sendWhatsAppReminder(dueId: number): Promise<void> {
    const popup = window.open('', '_blank');

    try {
        const { url } = await postJson<{ url: string; message: string }>(
            `/dues/${dueId}/reminders`,
        );

        if (popup) {
            popup.opener = null;
            popup.location.href = url;
        } else {
            window.open(url, '_blank', 'noopener');
        }

        toast.success('Reminder ready. WhatsApp is opening.');
    } catch (error) {
        popup?.close();
        toast.error(
            error instanceof Error
                ? error.message
                : 'The reminder could not be prepared.',
        );
    }
}
