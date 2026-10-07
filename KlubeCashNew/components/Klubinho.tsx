import Image from "next/image";

type KlubinhoProps = {
  pose: "aceno" | "comemora";
  size?: number;
  className?: string;
  floatAnimation?: boolean;
  priority?: boolean;
};

const poseSources = {
  aceno: "/mascote/klubinho-aceno.png",
  comemora: "/mascote/klubinho-comemora.png",
} as const;

export function Klubinho({
  pose,
  size = 200,
  className = "",
  floatAnimation = true,
  priority = false,
}: KlubinhoProps) {
  return (
    <div
      className={`klubinho ${floatAnimation ? "klubinho-float" : ""} ${className}`.trim()}
    >
      <Image
        src={poseSources[pose]}
        alt="Klubinho, o mascote da KlubeCash"
        width={size}
        height={Math.round(size * 1536 / 2752)}
        sizes={`${size}px`}
        className="klubinho-image"
        unoptimized
        priority={priority}
      />
    </div>
  );
}
