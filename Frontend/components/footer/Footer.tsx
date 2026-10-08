import Image from "next/image";

export default function Footer() {
    return (
        <footer className="flex flex-col items-center px-6 pb-4 pt-3">
            <Image
                src="/images/2o.png"
                alt="Organized by Crown Prince Foundation"
                width={180}
                height={100}
                className="h-auto w-44 object-contain"
            />

            <div className="mt-2 w-48 border-t border-border" />

            <p className="mt-2 text-center text-sm text-muted-foreground">
                Developed by{" "}

                <span className="cursor-default text-primary transition-opacity duration-300 hover:opacity-60">
                    Obada Saleh
                </span>

                <span className="mx-1 text-muted-foreground">&</span>

                <span className="cursor-default text-primary transition-opacity duration-300 hover:opacity-60">
                    Fuad Tamimi
                </span>
            </p>
        </footer>
    );
}