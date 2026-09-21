"use client";

import { useLayoutEffect, useState, type RefObject } from "react";

export type FloatingMenuPos = {
  top: number;
  left: number;
  width: number;
  maxHeight: number;
  placement: "bottom" | "top";
};

/** Remonte le DOM jusqu'au premier ancêtre réellement scrollable (ex: le
 * corps d'une modale) — pour ne pas confondre l'espace disponible avec
 * celui du viewport entier, qui ignore un footer juste en dessous. */
function findScrollParent(el: HTMLElement): HTMLElement | null {
  let node = el.parentElement;
  while (node && node !== document.body) {
    const style = window.getComputedStyle(node);
    const overflowY = style.overflowY;
    if (
      (overflowY === "auto" || overflowY === "scroll") &&
      node.scrollHeight > node.clientHeight
    ) {
      return node;
    }
    node = node.parentElement;
  }
  return null;
}

/** Positionne un menu en fixed hors overflow (modals, tables…). */
export function useFloatingMenu(
  open: boolean,
  triggerRef: RefObject<HTMLElement | null>,
  opts?: { minWidth?: number; maxMenuHeight?: number },
) {
  const minWidth = opts?.minWidth ?? 0;
  const maxMenuHeight = opts?.maxMenuHeight ?? 280;
  const [pos, setPos] = useState<FloatingMenuPos | null>(null);

  useLayoutEffect(() => {
    const close = () => setPos(null);
    if (!open) {
      close();
      return;
    }

    const update = () => {
      const el = triggerRef.current;
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const gap = 4;
      // Un menu dans une modale ne doit pas déborder sur le pied de page
      // (footer) qui suit le conteneur scrollable — se limiter au
      // viewport entier laisse le menu recouvrir des boutons visibles.
      const scrollParent = findScrollParent(el);
      const boundsBottom = scrollParent
        ? scrollParent.getBoundingClientRect().bottom
        : window.innerHeight;
      const spaceBelow = boundsBottom - rect.bottom - gap - 8;
      const spaceAbove = rect.top - gap - 8;
      const placement: "bottom" | "top" =
        spaceBelow >= 220 || spaceBelow >= spaceAbove ? "bottom" : "top";
      const available = placement === "bottom" ? spaceBelow : spaceAbove;
      setPos({
        top: placement === "bottom" ? rect.bottom + gap : rect.top - gap,
        left: Math.min(
          rect.left,
          Math.max(8, window.innerWidth - Math.max(rect.width, minWidth) - 8),
        ),
        width: Math.max(rect.width, minWidth),
        maxHeight: Math.max(140, Math.min(maxMenuHeight, available)),
        placement,
      });
    };

    update();
    window.addEventListener("resize", update);
    // capture scroll in modal bodies / nested scrollers
    window.addEventListener("scroll", update, true);
    return () => {
      window.removeEventListener("resize", update);
      window.removeEventListener("scroll", update, true);
    };
  }, [open, triggerRef, minWidth, maxMenuHeight]);

  return pos;
}
