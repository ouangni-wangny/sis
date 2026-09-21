"use client";

import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ComponentType,
  type ReactNode,
} from "react";
import { createPortal } from "react-dom";
import {
  ArrowRightCircle,
  CheckCircle2,
  FileDown,
  MapPin,
  MoreHorizontal,
  Pencil,
  Trash2,
  UserCog,
  type LucideProps,
} from "lucide-react";
import { useFloatingMenu } from "@/presentation/components/ui/useFloatingMenu";
import { cn } from "@/shared/lib/cn";

type IconType = ComponentType<LucideProps>;

export type TableActionTone =
  | "default"
  | "primary"
  | "accent"
  | "success"
  | "warning"
  | "danger";

export type TableActionItem = {
  key?: string;
  label: string;
  onClick: () => void;
  icon?: IconType;
  tone?: TableActionTone;
  disabled?: boolean;
  hidden?: boolean;
};

const toneIconClass: Record<TableActionTone, string> = {
  default: "text-ink-muted",
  primary: "text-teal",
  accent: "text-brand-accent",
  success: "text-teal-dark",
  warning: "text-warning",
  danger: "text-danger",
};

const toneItemClass: Record<TableActionTone, string> = {
  default: "text-ink hover:bg-paper",
  primary: "text-ink hover:bg-teal/5",
  accent: "text-ink hover:bg-brand-accent/5",
  success: "text-ink hover:bg-teal/5",
  warning: "text-ink hover:bg-warning/5",
  danger: "text-danger hover:bg-danger/5",
};

export const tableActionIcons = {
  edit: Pencil,
  delete: Trash2,
  download: FileDown,
  map: MapPin,
  assign: UserCog,
  advance: ArrowRightCircle,
  resolve: CheckCircle2,
} as const;

export function TableActions({
  onEdit,
  onDelete,
  canEdit = true,
  canDelete = true,
  editLabel = "Modifier",
  deleteLabel = "Supprimer",
  items,
  className,
}: {
  onEdit?: () => void;
  onDelete?: () => void;
  canEdit?: boolean;
  canDelete?: boolean;
  editLabel?: string;
  deleteLabel?: string;
  /** Actions supplémentaires (ou seules actions si pas d’edit/delete). */
  items?: TableActionItem[];
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const menuId = useId();
  const pos = useFloatingMenu(open, triggerRef, {
    minWidth: 200,
    maxMenuHeight: 320,
  });

  const actions = useMemo(() => {
    const list: TableActionItem[] = [];

    if (canEdit && onEdit) {
      list.push({
        key: "edit",
        label: editLabel,
        icon: Pencil,
        tone: "primary",
        onClick: onEdit,
      });
    }

    for (const item of items ?? []) {
      if (!item.hidden) list.push(item);
    }

    if (canDelete && onDelete) {
      list.push({
        key: "delete",
        label: deleteLabel,
        icon: Trash2,
        tone: "danger",
        onClick: onDelete,
      });
    }

    return list;
  }, [
    canEdit,
    canDelete,
    onEdit,
    onDelete,
    editLabel,
    deleteLabel,
    items,
  ]);

  useEffect(() => {
    if (!open) return;

    const onPointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (triggerRef.current?.contains(target)) return;
      if (menuRef.current?.contains(target)) return;
      setOpen(false);
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  if (actions.length === 0) {
    return <span className="text-xs text-ink-faint">—</span>;
  }

  const menu: ReactNode =
    open && pos
      ? createPortal(
          <div
            ref={menuRef}
            id={menuId}
            role="menu"
            style={{
              position: "fixed",
              top:
                pos.placement === "bottom"
                  ? pos.top
                  : undefined,
              bottom:
                pos.placement === "top"
                  ? window.innerHeight - pos.top
                  : undefined,
              left: Math.min(
                Math.max(8, pos.left + pos.width - Math.max(pos.width, 200)),
                window.innerWidth - Math.max(pos.width, 200) - 8,
              ),
              width: Math.max(pos.width, 200),
              maxHeight: pos.maxHeight,
            }}
            className="z-[80] overflow-auto rounded-lg border border-border bg-white py-1 shadow-lg shadow-ink/10 animate-fade-in"
          >
            {actions.map((action, index) => {
              const Icon = action.icon;
              const tone = action.tone ?? "default";
              return (
                <button
                  key={action.key ?? `${action.label}-${index}`}
                  type="button"
                  role="menuitem"
                  disabled={action.disabled}
                  className={cn(
                    "flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-40",
                    toneItemClass[tone],
                  )}
                  onClick={() => {
                    if (action.disabled) return;
                    setOpen(false);
                    action.onClick();
                  }}
                >
                  {Icon ? (
                    <Icon
                      className={cn("size-4 shrink-0", toneIconClass[tone])}
                      aria-hidden
                    />
                  ) : (
                    <span className="size-4 shrink-0" />
                  )}
                  <span className="min-w-0 flex-1 truncate">{action.label}</span>
                </button>
              );
            })}
          </div>,
          document.body,
        )
      : null;

  return (
    <div className={cn("flex justify-end", className)}>
      <button
        ref={triggerRef}
        type="button"
        aria-label="Actions"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        title="Actions"
        className={cn(
          "inline-flex size-8 items-center justify-center rounded-md border border-transparent text-ink-muted transition",
          "hover:border-border hover:bg-paper hover:text-ink",
          open && "border-border bg-paper text-ink",
        )}
        onClick={() => setOpen((v) => !v)}
      >
        <MoreHorizontal className="size-4" />
      </button>
      {menu}
    </div>
  );
}
