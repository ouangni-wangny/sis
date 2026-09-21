/** Relève 48h contrôleurs : 2 jours en service / 2 jours repos. */

export const CONTROLEUR_RELEVE_BLOC_JOURS = 2;
export const CONTROLEUR_RELEVE_MAX_PAR_ZONE = 2;

function startOfDay(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function parseYmd(ymd: string): Date {
  const [y, m, day] = ymd.split("-").map(Number);
  return new Date(y, m - 1, day);
}

export function calendarDaysBetween(depuis: string, jour: Date | string): number {
  const a = startOfDay(typeof depuis === "string" ? parseYmd(depuis) : depuis);
  const b = startOfDay(typeof jour === "string" ? parseYmd(jour) : jour);
  return Math.round((b.getTime() - a.getTime()) / 86_400_000);
}

export function indiceEnService(depuis: string, jour: Date | string = new Date()): number {
  const dayIndex = calendarDaysBetween(depuis, jour);
  if (dayIndex < 0) return -1; // avant le début du cycle
  return Math.floor(dayIndex / CONTROLEUR_RELEVE_BLOC_JOURS) % CONTROLEUR_RELEVE_MAX_PAR_ZONE;
}

export function estControleurEnService(
  indiceReleve: number | null | undefined,
  releveDepuis: string | null | undefined,
  jour: Date | string = new Date(),
): boolean {
  if (indiceReleve == null || !releveDepuis) return true;
  const idx = indiceEnService(releveDepuis, jour);
  if (idx < 0) return false;
  return idx === indiceReleve;
}

/** Prochains blocs explicites dans [depuis, jusque]. */
export function nextReleveBlocks(
  depuis: string,
  from: Date | string = new Date(),
  count = 4,
  jusque?: string | null,
): Array<{ indice: 0 | 1; start: string; end: string }> {
  const fromIso =
    typeof from === "string" ? from.slice(0, 10) : toYmd(from);
  let cursor = fromIso < depuis ? depuis : fromIso;
  const dayIndex = Math.max(0, calendarDaysBetween(depuis, cursor));
  const blocOffset = dayIndex % CONTROLEUR_RELEVE_BLOC_JOURS;
  if (blocOffset > 0) {
    cursor = addDaysYmd(cursor, -blocOffset);
  }
  const out: Array<{ indice: 0 | 1; start: string; end: string }> = [];
  for (let i = 0; i < count; i++) {
    if (jusque && cursor > jusque) break;
    const idx = indiceEnService(depuis, cursor);
    const start = cursor;
    let end = addDaysYmd(cursor, CONTROLEUR_RELEVE_BLOC_JOURS - 1);
    if (jusque && end > jusque) end = jusque;
    if (jusque && start > jusque) break;
    out.push({ indice: (idx < 0 ? 0 : idx) as 0 | 1, start, end });
    cursor = addDaysYmd(cursor, CONTROLEUR_RELEVE_BLOC_JOURS);
  }
  return out;
}

export function labelReleve48h(
  indiceReleve: number | null | undefined,
  releveDepuis: string | null | undefined,
  jour: Date | string = new Date(),
): string | null {
  if (indiceReleve == null || !releveDepuis) return null;
  return estControleurEnService(indiceReleve, releveDepuis, jour)
    ? "En service"
    : "Repos";
}

function toYmd(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function addDaysYmd(iso: string, days: number): string {
  const d = parseYmd(iso);
  d.setDate(d.getDate() + days);
  return toYmd(d);
}
