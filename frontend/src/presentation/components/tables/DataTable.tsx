"use client";

import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
  type RowSelectionState,
} from "@tanstack/react-table";
import { ChevronLeft, ChevronRight, Search } from "lucide-react";
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Button } from "@/presentation/components/ui/Button";
import { EmptyState } from "@/presentation/components/ui/EmptyState";
import { Input } from "@/presentation/components/ui/Input";
import { cn } from "@/shared/lib/cn";

export type DataTablePagination = {
  page: number;
  perPage: number;
  total: number;
  onPageChange: (page: number) => void;
  /** Affiche le sélecteur « lignes par page » si fourni. */
  onPerPageChange?: (perPage: number) => void;
  /** Options du sélecteur (défaut : 15, 25, 50, 100, 200). */
  perPageOptions?: number[];
};

const DEFAULT_PER_PAGE_OPTIONS = [15, 25, 50, 100, 200];

type DataTableProps<T> = {
  data: T[];
  columns: ColumnDef<T, unknown>[];
  isLoading?: boolean;
  pagination?: DataTablePagination;
  search?: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
  };
  toolbar?: ReactNode;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyAction?: ReactNode;
  /** Cases à cocher en début de ligne (défaut : true). */
  selectable?: boolean;
  getRowId?: (row: T) => string;
  onSelectionChange?: (ids: string[]) => void;
};

function defaultRowId<T>(row: T, index: number): string {
  if (row && typeof row === "object" && "id" in row) {
    const id = (row as { id?: unknown }).id;
    if (typeof id === "string" || typeof id === "number") return String(id);
  }
  return String(index);
}

export function DataTable<T>({
  data,
  columns,
  isLoading,
  pagination,
  search,
  toolbar,
  emptyTitle,
  emptyDescription,
  emptyAction,
  selectable = true,
  getRowId,
  onSelectionChange,
}: DataTableProps<T>) {
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const onSelectionChangeRef = useRef(onSelectionChange);
  onSelectionChangeRef.current = onSelectionChange;
  const prevSelectionKeyRef = useRef<string>("");

  // Signature stable : évite une boucle si `data` est un nouveau [] à chaque render.
  const dataSignature = useMemo(
    () =>
      data
        .map((row, index) =>
          getRowId ? getRowId(row) : defaultRowId(row, index),
        )
        .join("|"),
    [data, getRowId],
  );

  useEffect(() => {
    setRowSelection({});
  }, [dataSignature]);

  useEffect(() => {
    const ids = Object.keys(rowSelection).filter((id) => rowSelection[id]);
    const key = ids.join("|");
    if (key === prevSelectionKeyRef.current) return;
    prevSelectionKeyRef.current = key;
    onSelectionChangeRef.current?.(ids);
  }, [rowSelection]);

  const selectionColumn = useMemo<ColumnDef<T, unknown>>(
    () => ({
      id: "select",
      enableSorting: false,
      header: ({ table }) => (
        <input
          type="checkbox"
          className="size-4 cursor-pointer rounded border-border accent-teal"
          checked={table.getIsAllPageRowsSelected()}
          ref={(el) => {
            if (el) {
              el.indeterminate = table.getIsSomePageRowsSelected();
            }
          }}
          onChange={table.getToggleAllPageRowsSelectedHandler()}
          aria-label="Tout sélectionner"
          onClick={(e) => e.stopPropagation()}
        />
      ),
      cell: ({ row }) => (
        <input
          type="checkbox"
          className="size-4 cursor-pointer rounded border-border accent-teal"
          checked={row.getIsSelected()}
          disabled={!row.getCanSelect()}
          onChange={row.getToggleSelectedHandler()}
          aria-label="Sélectionner la ligne"
          onClick={(e) => e.stopPropagation()}
        />
      ),
    }),
    [],
  );

  const tableColumns = useMemo(
    () => (selectable ? [selectionColumn, ...columns] : columns),
    [selectable, selectionColumn, columns],
  );

  const table = useReactTable({
    data,
    columns: tableColumns,
    getCoreRowModel: getCoreRowModel(),
    enableRowSelection: selectable,
    onRowSelectionChange: setRowSelection,
    getRowId: (row, index) =>
      getRowId ? getRowId(row) : defaultRowId(row, index),
    state: {
      rowSelection,
    },
    manualPagination: true,
    pageCount: pagination
      ? Math.max(1, Math.ceil(pagination.total / pagination.perPage))
      : 1,
  });

  const lastPage = pagination
    ? Math.max(1, Math.ceil(pagination.total / pagination.perPage))
    : 1;

  const colCount = tableColumns.length;

  return (
    <div className="overflow-hidden rounded-lg border border-border bg-white/90 shadow-sm">
      {(search || toolbar) && (
        <div className="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center">
          {search ? (
            <div className="relative min-w-0 flex-1 sm:max-w-md">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
              <Input
                value={search.value}
                onChange={(e) => search.onChange(e.target.value)}
                placeholder={search.placeholder ?? "Rechercher…"}
                className="h-10 pl-9"
                aria-label="Recherche"
              />
            </div>
          ) : (
            <div className="hidden min-w-0 flex-1 sm:block" />
          )}
          {toolbar ? (
            <div className="flex flex-wrap items-center gap-2 sm:ml-auto sm:shrink-0 [&_button]:h-10 [&_button]:px-4 [&_button]:text-sm">
              {toolbar}
            </div>
          ) : null}
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] border-collapse text-left text-sm">
          <thead>
            {table.getHeaderGroups().map((hg) => (
              <tr key={hg.id} className="border-b border-border bg-paper-muted/60">
                {hg.headers.map((header) => (
                  <th
                    key={header.id}
                    className={cn(
                      "px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-ink-muted",
                      header.column.id === "select" && "w-10 px-3",
                      header.column.id === "actions" &&
                        "sticky right-0 z-10 bg-paper-muted/95 text-right shadow-[-6px_0_8px_-6px_rgba(0,0,0,0.08)]",
                    )}
                  >
                    {header.isPlaceholder
                      ? null
                      : flexRender(
                          header.column.columnDef.header,
                          header.getContext(),
                        )}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {isLoading
              ? Array.from({ length: 6 }).map((_, i) => (
                  <tr key={i} className="border-b border-border/70">
                    {tableColumns.map((_, j) => (
                      <td key={j} className="px-4 py-3">
                        <div className="h-4 animate-pulse rounded bg-paper-muted" />
                      </td>
                    ))}
                  </tr>
                ))
              : null}

            {!isLoading && data.length === 0 ? (
              <tr>
                <td colSpan={colCount}>
                  <EmptyState
                    title={emptyTitle}
                    description={emptyDescription}
                    action={emptyAction}
                  />
                </td>
              </tr>
            ) : null}

            {!isLoading &&
              table.getRowModel().rows.map((row) => (
                <tr
                  key={row.id}
                  data-selected={row.getIsSelected() || undefined}
                  className={cn(
                    "group border-b border-border/70 transition-colors hover:bg-paper-muted/40",
                    row.getIsSelected() && "bg-teal/[0.04]",
                  )}
                >
                  {row.getVisibleCells().map((cell) => (
                    <td
                      key={cell.id}
                      className={cn(
                        "px-4 py-2.5 text-ink",
                        cell.column.id === "select" && "w-10 px-3",
                        cell.column.id === "actions" &&
                          "sticky right-0 z-10 bg-white text-right shadow-[-6px_0_8px_-6px_rgba(0,0,0,0.08)] group-hover:bg-paper-muted/40 group-data-[selected]:bg-teal/[0.04]",
                      )}
                    >
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext(),
                      )}
                    </td>
                  ))}
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {pagination ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-2.5 text-xs text-ink-muted">
          <div className="flex flex-wrap items-center gap-3">
            <p>
              {pagination.total === 0
                ? "0 résultat"
                : `${(pagination.page - 1) * pagination.perPage + 1}–${Math.min(
                    pagination.page * pagination.perPage,
                    pagination.total,
                  )} sur ${pagination.total}`}
              {selectable && Object.keys(rowSelection).length > 0
                ? ` · ${Object.keys(rowSelection).length} sélectionné${Object.keys(rowSelection).length > 1 ? "s" : ""}`
                : ""}
            </p>
            {pagination.onPerPageChange ? (
              <label className="inline-flex items-center gap-1.5">
                <span className="text-ink-faint">Par page</span>
                <select
                  className="h-8 rounded-md border border-border bg-white px-2 text-xs font-medium text-ink outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20"
                  value={pagination.perPage}
                  disabled={isLoading}
                  aria-label="Nombre de lignes par page"
                  onChange={(e) => {
                    const next = Number(e.target.value);
                    if (!Number.isFinite(next) || next <= 0) return;
                    pagination.onPerPageChange?.(next);
                  }}
                >
                  {(pagination.perPageOptions ?? DEFAULT_PER_PAGE_OPTIONS).map(
                    (n) => (
                      <option key={n} value={n}>
                        {n}
                      </option>
                    ),
                  )}
                  {/* Garde la valeur courante si hors options (ex. API renvoie 20). */}
                  {!(pagination.perPageOptions ?? DEFAULT_PER_PAGE_OPTIONS).includes(
                    pagination.perPage,
                  ) ? (
                    <option value={pagination.perPage}>
                      {pagination.perPage}
                    </option>
                  ) : null}
                </select>
              </label>
            ) : null}
          </div>
          <div className="flex items-center gap-1">
            <Button
              variant="secondary"
              size="sm"
              disabled={pagination.page <= 1 || isLoading}
              onClick={() => pagination.onPageChange(pagination.page - 1)}
              aria-label="Page précédente"
            >
              <ChevronLeft className="size-4" />
            </Button>
            <span className="min-w-16 text-center font-mono tabular-nums">
              {pagination.page} / {lastPage}
            </span>
            <Button
              variant="secondary"
              size="sm"
              disabled={pagination.page >= lastPage || isLoading}
              onClick={() => pagination.onPageChange(pagination.page + 1)}
              aria-label="Page suivante"
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
