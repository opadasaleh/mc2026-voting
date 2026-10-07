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
                relative cursor-pointer overflow-hidden pt-0
                transition-all duration-300
                ${selected
                    ? "ring-4 ring-[var(--primary)]"
                    : "hover:-translate-y-1"
                }
            `}
        >
            <img
                src={photoUrl}
                alt={name}
                className="aspect-video w-full object-cover"
            />

            <CardHeader>
                <CardTitle>
                    {name}
                </CardTitle>

                <CardDescription>
                    {description}
                </CardDescription>
            </CardHeader>

            {selected && (
                <div
                    className="
                        absolute right-3 top-3
                        flex h-8 w-8 items-center justify-center
                        rounded-full
                        bg-[var(--primary)]
                        text-white
                    "
                >
                    ✓
                </div>
            )}
        </Card>
    );
}