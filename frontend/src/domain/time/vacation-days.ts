import {
  addOneDayIso,
  isCycle24h,
  isOvernight,
  normalizeHm,
  rangesOverlapMs,
} from "@/domain/time/shift-interval";

/**
 * Jours de prise de poste d’une vacation — aligné sur ShiftSchedule (API).
 *
 * Une vacation sur période = un créneau identique par jour, pas un bloc continu :
 * - jour            : date_debut … date_fin
 * - nuit / cycle 24h : date_debut … date_fin − 1 (le dernier créneau finit le matin de date_fin)
 * - date_fin null   : affectation continue (tous les jours à partir de date_debut)
 */
export type VacationDaysInput = {
  date_debut: string;
  date_fin?: string | null;
  heure_debut: string;
  heure_fin: string;
};

function hm(time: string): string {
  return normalizeHm(time) || time.slice(0, 5);
}

export function minusOneDayIso(iso: string): string {
  const d = new Date(`${iso.slice(0, 10)}T12:00:00`);
  d.setDate(d.getDate() - 1);
  return d.toISOString().slice(0, 10);
}

/** Dernier jour de prise de poste (null = sans fin). */
export function lastPriseDePosteDay(v: VacationDaysInput): string | null {
  const first = v.date_debut.slice(0, 10);
  if (!v.date_fin?.trim()) return null;
  const fin = v.date_fin.slice(0, 10);
  const hd = hm(v.heure_debut);
  const hf = hm(v.heure_fin);
  const last =
    isOvernight(hd, hf) || isCycle24h(hd, hf) ? minusOneDayIso(fin) : fin;
  return last < first ? first : last;
}

/** La vacation a-t-elle une prise de poste ce jour-là ? */
export function vacationCoversDay(v: VacationDaysInput, dayIso: string): boolean {
  const day = dayIso.slice(0, 10);
  const first = v.date_debut.slice(0, 10);
  if (day < first) return false;
  const last = lastPriseDePosteDay(v);
  return last === null || day <= last;
}

/** Jours de prise de poste compris entre `from` et `to` (inclus). */
export function priseDePosteDaysBetween(
  v: VacationDaysInput,
  from: string,
  to: string,
): string[] {
  const out: string[] = [];
  let cur = from.slice(0, 10);
  const end = to.slice(0, 10);
  while (cur <= end) {
    if (vacationCoversDay(v, cur)) out.push(cur);
    cur = addOneDayIso(cur);
  }
  return out;
}

/** Créneau [début, fin] en ms de la prise de poste du jour `dayIso`. */
export function shiftWindowOnDayMs(
  dayIso: string,
  heureDebut: string,
  heureFin: string,
): [number, number] {
  const day = dayIso.slice(0, 10);
  const hd = hm(heureDebut);
  const hf = hm(heureFin);
  const start = new Date(`${day}T${hd}:00`).getTime();
  const endDay =
    isOvernight(hd, hf) || isCycle24h(hd, hf) ? addOneDayIso(day) : day;
  return [start, new Date(`${endDay}T${hf}:00`).getTime()];
}

/**
 * Créneaux d’une vacation proches d’un jour donné (veille, jour, lendemain) —
 * de quoi tester chevauchement et repos avec un créneau proposé ce jour-là.
 */
export function vacationWindowsAroundDayMs(
  v: VacationDaysInput,
  dayIso: string,
): Array<[number, number]> {
  const day = dayIso.slice(0, 10);
  return [minusOneDayIso(day), day, addOneDayIso(day)]
    .filter((d) => vacationCoversDay(v, d))
    .map((d) => shiftWindowOnDayMs(d, v.heure_debut, v.heure_fin));
}

/** Un créneau de la vacation chevauche-t-il la plage proposée ? */
export function vacationOverlapsWindow(
  v: VacationDaysInput,
  dayIso: string,
  proposed: [number, number],
): boolean {
  return vacationWindowsAroundDayMs(v, dayIso).some((w) =>
    rangesOverlapMs(w, proposed),
  );
}
