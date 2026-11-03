/** Date locale du téléphone (Y-m-d), telle que l’attend l’API. */
export function dateDuJour(): string {
  const d = new Date();
  const mois = String(d.getMonth() + 1).padStart(2, "0");
  const jour = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${mois}-${jour}`;
}
