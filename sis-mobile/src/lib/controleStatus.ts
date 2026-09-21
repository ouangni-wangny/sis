/** Map agent_id → dernier contrôle du jour (le plus récent en tête de liste). */
export function mapControlesByAgent(
  controles: { controle_agent_id: string; resultat: string; effectue_at?: string | null }[],
) {
  const map = new Map<string, { resultat: "present" | "absent" | "enregistre"; effectue_at?: string | null }>();
  for (const c of controles) {
    if (!map.has(c.controle_agent_id)) {
      map.set(c.controle_agent_id, {
        resultat: c.resultat as "present" | "absent" | "enregistre",
        effectue_at: c.effectue_at,
      });
    }
  }
  return map;
}
