import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

type VoteAlertProps = {
    disabled: boolean;
    exhibitorName: string;
    onConfirm: () => void;
};

export function VoteAlert({
    disabled,
    exhibitorName,
    onConfirm,
}: VoteAlertProps) {
    return (
        <AlertDialog>
            <AlertDialogTrigger
                disabled={disabled}
                className="
                    inline-flex h-10 w-full
                    items-center justify-center
                    rounded-md
                    bg-[var(--primary)]
                    px-4
                    font-medium text-white
                    disabled:pointer-events-none
                    disabled:opacity-50
                "
            >
                Submit Vote
            </AlertDialogTrigger>

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Confirm your vote
                    </AlertDialogTitle>

                    <AlertDialogDescription>
                        You are voting for{" "}
                        <strong>{exhibitorName}</strong>.
                        Your vote cannot be changed after submission.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel>
                        Cancel
                    </AlertDialogCancel>

                    <AlertDialogAction onClick={onConfirm}>
                        Confirm Vote
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}