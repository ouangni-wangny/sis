"use client";

import { useState } from "react";
import { ImageOff } from "lucide-react";
import { cn } from "@/shared/lib/cn";

/**
 * Preuve photo d’un contrôle : vignette cliquable, avec repli propre (icône)
 * si l’image ne se charge pas — jamais de texte alternatif qui déborde.
 */
export function PhotoPreuve({
  photo,
  className,
}: {
  photo?: { url: string; thumb?: string | null } | null;
  className?: string;
}) {
  const [erreurVignette, setErreurVignette] = useState(false);

  if (!photo?.url) {
    return <span className="text-ink-faint">—</span>;
  }

  const src = photo.thumb || photo.url;

  return (
    <a
      href={photo.url}
      target="_blank"
      rel="noreferrer"
      className={cn(
        "inline-flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-teal hover:underline",
        className,
      )}
      title="Ouvrir la photo du contrôle"
    >
      <span className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded border border-border bg-paper-muted text-ink-faint">
        {erreurVignette ? (
          <ImageOff className="size-4" aria-hidden />
        ) : (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={src}
            alt=""
            loading="lazy"
            className="size-full object-cover"
            onError={() => setErreurVignette(true)}
          />
        )}
      </span>
      Voir photo
    </a>
  );
}
