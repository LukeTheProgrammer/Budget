import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/accounts';
import type { AccountListItem } from '@/types/accounts';

type DeleteAccountDialogProps = {
    /** The account pending deletion, or null when the dialog is closed. */
    account: AccountListItem | null;
    onClose: () => void;
};

export function DeleteAccountDialog({ account, onClose }: DeleteAccountDialogProps) {
    const { delete: destroyAccount, processing } = useForm();

    function confirmDelete(): void {
        if (!account) {
            return;
        }

        destroyAccount(destroy({ account: account.id }).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Dialog
            open={account !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete account</DialogTitle>
                    <DialogDescription>
                        Delete {account?.name}? It and its transactions will be hidden from your views. This cannot be
                        undone from here.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" onClick={confirmDelete} disabled={processing}>
                        Delete account
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
