import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

type ExhibitorCardProps = {
    name: string;
    description: string;
    photoUrl: string;
    selected: boolean;
    onSelect: () => void;
};

export function ExhibitorCard({
    name,
    description,
    photoUrl,
    selected,
    onSelect,
}: ExhibitorCardProps) {
    return (
        <Card
            onClick={onSelect}
            className={`
                relative cursor-pointer overflow-hidden p-0
                transition-all duration-200
                ${selected
                    ? "ring-3 ring-[var(--primary)]"
                    : "hover:-translate-y-1"
                }
            `}
        >
            <div className="relative aspect-[4/3] w-full overflow-hidden">
                <img
                    src={photoUrl}
                    alt={name}
                    className="h-full w-full object-cover"
                />

                {selected && (
                    <div
                        className="
                            absolute right-3 top-3
                            flex h-8 w-8 items-center justify-center
                            rounded-full
                            bg-[var(--primary)]
                            font-bold text-white
                        "
                    >
                        ✓
                    </div>
                )}
            </div>

            <CardHeader className="gap-1 p-4">
                <CardTitle className="text-base">
                    {name}
                </CardTitle>

                <CardDescription className="line-clamp-2 text-sm">
                    {description}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}