import { useCallback, useMemo, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useFocusEffect } from "expo-router";
import { getErrorMessage, useAuth } from "@/auth/AuthContext";
import { vacationsApi } from "@/api/resources";
import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { Field } from "@/components/Field";
import { ScreenShell } from "@/components/ScreenShell";
import { dateDuJour } from "@/lib/dateDuJour";
import { colors, radii, spacing, typography } from "@/theme/tokens";
import type { Remplacant, Vacation } from "@/types/api";

function creneau(v: Vacation) {
  return `${v.heure_debut.slice(0, 5)}–${v.heure_fin.slice(0, 5)}`;
}

function raison(r: Remplacant) {
  if (r.descend) {
    return r.meme_poste
      ? "Descend de ce poste · heures supplémentaires"
      : "Descend sur ce site · heures supplémentaires";
  }
  return r.pool_siege ? "Pool siège" : (r.matricule ?? "");
}

/** Absence constatée sur le terrain : le contrôleur désigne qui prend le poste. */
export default function RecouvrirScreen() {
  const { token } = useAuth();
  const [trous, setTrous] = useState<Vacation[]>([]);
  const [trou, setTrou] = useState<Vacation | null>(null);
  const [remplacants, setRemplacants] = useState<Remplacant[]>([]);
  const [choisi, setChoisi] = useState<string | null>(null);
  const [recherche, setRecherche] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    setRefreshing(true);
    setError(null);
    try {
      const res = await vacationsApi.aRecouvrir(token, dateDuJour());
      setTrous(res.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setRefreshing(false);
    }
  }, [token]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const ouvrir = async (v: Vacation) => {
    if (!token) return;
    setError(null);
    setOk(null);
    setChoisi(null);
    setRecherche("");
    setRemplacants([]);
    setTrou(v);
    try {
      const res = await vacationsApi.remplacants(token, v.id);
      setRemplacants(res.data);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  };

  const filtres = useMemo(() => {
    const terme = recherche.trim().toLowerCase();
    if (!terme) return remplacants;
    return remplacants.filter((r) =>
      `${r.agent} ${r.matricule ?? ""}`.toLowerCase().includes(terme),
    );
  }, [remplacants, recherche]);

  const confirmer = async () => {
    if (!token || !trou || !choisi) return;
    setSaving(true);
    setError(null);
    try {
      await vacationsApi.recouvrir(token, trou.id, choisi);
      const nom = remplacants.find((r) => r.agent_id === choisi)?.agent;
      setOk(`${nom ?? "L’agent"} prend le poste ${trou.poste?.nom ?? ""}.`);
      setTrou(null);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScreenShell refreshing={refreshing} onRefresh={load}>
      {error ? (
        <View style={styles.errorBox}>
          <Ionicons name="alert-circle" size={16} color={colors.danger} />
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}
      {ok ? (
        <View style={styles.okBox}>
          <Ionicons name="checkmark-circle" size={16} color={colors.teal} />
          <Text style={styles.okText}>{ok}</Text>
        </View>
      ) : null}

      {!trou ? (
        trous.length === 0 ? (
          <EmptyState
            title="Aucun poste à recouvrir"
            description="Les absences d’aujourd’hui sur vos sites apparaîtront ici."
          />
        ) : (
          <View style={styles.list}>
            {trous.map((v) => (
              <Pressable
                key={v.id}
                onPress={() => void ouvrir(v)}
                style={({ pressed }) => [
                  styles.row,
                  pressed && { opacity: 0.85 },
                ]}
              >
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle} numberOfLines={1}>
                    {v.site?.nom}
                    {v.poste ? ` · ${v.poste.nom}` : ""}
                  </Text>
                  <Text style={styles.rowMeta} numberOfLines={1}>
                    {creneau(v)} · {v.agent?.prenom} {v.agent?.nom} absent
                  </Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color={colors.inkFaint} />
              </Pressable>
            ))}
          </View>
        )
      ) : (
        <View style={styles.choix}>
          <Text style={styles.titre}>
            {trou.site?.nom}
            {trou.poste ? ` · ${trou.poste.nom}` : ""}
          </Text>
          <Text style={styles.rowMeta}>
            {creneau(trou)} · remplace {trou.agent?.prenom} {trou.agent?.nom}
          </Text>

          <Field
            label="Rechercher un agent"
            value={recherche}
            onChangeText={setRecherche}
            placeholder="Nom ou matricule"
            autoCorrect={false}
          />

          {filtres.length === 0 ? (
            <EmptyState
              title="Aucun agent libre"
              description="Prévenez le planning : personne n’est disponible sur ce créneau."
            />
          ) : (
            <View style={styles.list}>
              {filtres.map((r) => {
                const actif = r.agent_id === choisi;
                return (
                  <Pressable
                    key={r.agent_id}
                    onPress={() => setChoisi(r.agent_id)}
                    accessibilityRole="radio"
                    accessibilityState={{ selected: actif }}
                    style={[styles.row, actif && styles.rowActif]}
                  >
                    <View style={styles.rowCopy}>
                      <Text style={styles.rowTitle} numberOfLines={1}>
                        {r.agent}
                      </Text>
                      <Text style={styles.rowMeta} numberOfLines={1}>
                        {raison(r)}
                      </Text>
                    </View>
                    {actif ? (
                      <Ionicons name="checkmark-circle" size={20} color={colors.teal} />
                    ) : null}
                  </Pressable>
                );
              })}
            </View>
          )}

          <Button
            label="Confirmer le remplaçant"
            onPress={() => void confirmer()}
            loading={saving}
            disabled={!choisi}
          />
          <Button
            label="Retour"
            variant="secondary"
            disabled={saving}
            onPress={() => setTrou(null)}
          />
        </View>
      )}
    </ScreenShell>
  );
}

const styles = StyleSheet.create({
  list: { gap: spacing.sm },
  choix: { gap: spacing.md },
  titre: { ...typography.subtitle, color: colors.ink },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.md,
    padding: spacing.md,
  },
  rowActif: { borderColor: colors.teal, backgroundColor: colors.tealSoft },
  rowCopy: { flex: 1, gap: 2 },
  rowTitle: { ...typography.bodyStrong, color: colors.ink },
  rowMeta: { ...typography.caption, color: colors.inkMuted },
  errorBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    backgroundColor: colors.dangerSoft,
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  errorText: { ...typography.caption, color: colors.danger, flex: 1 },
  okBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    backgroundColor: colors.tealSoft,
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  okText: { ...typography.caption, color: colors.teal, flex: 1 },
});
