import { useCallback, useMemo, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import { Ionicons } from "@expo/vector-icons";
import { useFocusEffect, router } from "expo-router";
import { getErrorMessage, useAuth } from "@/auth/AuthContext";
import { controlesApi, vacationsApi } from "@/api/resources";
import { Avatar } from "@/components/Avatar";
import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { ScreenShell } from "@/components/ScreenShell";
import { ControlesPassagePill } from "@/components/StatusPill";
import {
  hintPassagesRequis,
  indexControlesParPassage,
  statusControleVacation,
  type PassageStatus,
} from "@/lib/controlePassages";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";
import type { Vacation } from "@/types/api";

export default function HomeScreen() {
  const { user, token } = useAuth();
  const [vacations, setVacations] = useState<Vacation[]>([]);
  const [statusByVacation, setStatusByVacation] = useState(
    () => new Map<string, PassageStatus>(),
  );
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    setRefreshing(true);
    setError(null);
    try {
      const [vacRes, ctrlRes] = await Promise.all([
        vacationsApi.enPoste(token),
        controlesApi.todayMine(token),
      ]);
      setVacations(vacRes.data);
      const byAgent = indexControlesParPassage(
        ctrlRes.data.map((c) => ({
          controle_agent_id: c.controle_agent_id,
          resultat: c.resultat,
          effectue_at: c.effectue_at,
        })),
      );
      const map = new Map<string, PassageStatus>();
      for (const v of vacRes.data) {
        map.set(v.id, statusControleVacation(v, byAgent));
      }
      setStatusByVacation(map);
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

  const hour = new Date().getHours();
  const greeting =
    hour < 12 ? "Bonjour" : hour < 18 ? "Bon après-midi" : "Bonsoir";

  const todayLabel = useMemo(
    () =>
      new Date().toLocaleDateString("fr-FR", {
        weekday: "long",
        day: "numeric",
        month: "long",
      }),
    [],
  );

  const remaining = useMemo(
    () => vacations.filter((v) => !statusByVacation.get(v.id)?.complet).length,
    [vacations, statusByVacation],
  );
  const doneCount = useMemo(
    () => vacations.filter((v) => statusByVacation.get(v.id)?.complet).length,
    [vacations, statusByVacation],
  );

  const preview = vacations.slice(0, 4);
  const hasMore = vacations.length > preview.length;
  const noPerimetre = (user?.agent?.perimetres?.length ?? 0) === 0;

  return (
    <ScreenShell refreshing={refreshing} onRefresh={load}>
      <View style={styles.identity}>
        <View style={styles.identityText}>
          <Text style={styles.greeting}>{greeting}</Text>
          <Text style={styles.name} numberOfLines={1}>
            {user?.prenom} {user?.nom}
          </Text>
          <Text style={styles.date}>{todayLabel}</Text>
        </View>
        <Pressable
          onPress={() => router.push("/(app)/profil")}
          style={styles.avatarTap}
          accessibilityLabel="Ouvrir le profil"
        >
          <Avatar prenom={user?.prenom} nom={user?.nom} size={52} />
        </Pressable>
      </View>

      {error ? (
        <View style={styles.errorBox}>
          <Ionicons name="alert-circle" size={16} color={colors.danger} />
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}

      <LinearGradient
        colors={[colors.tealDark, colors.teal]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.mission}
      >
        <Text style={styles.missionKicker}>Mission du jour</Text>
        <View style={styles.missionRow}>
          <Text style={styles.missionCount}>{remaining}</Text>
          <View style={styles.missionCopy}>
            <Text style={styles.missionTitle}>
              restant{remaining > 1 ? "s" : ""} à contrôler
            </Text>
            <Text style={styles.missionMeta}>
              {doneCount > 0
                ? `${doneCount} déjà contrôlé${doneCount > 1 ? "s" : ""} aujourd’hui`
                : "Aucun contrôle enregistré pour l’instant"}
            </Text>
          </View>
        </View>
        <Button
          label={
            vacations.length > 0
              ? "Commencer les contrôles"
              : "Voir les contrôles"
          }
          onPress={() => router.push("/(app)/controle")}
          variant="secondary"
          style={styles.missionCta}
        />
      </LinearGradient>

      <View style={styles.section}>
        <View style={styles.sectionHead}>
          <Text style={styles.sectionTitle}>À vérifier</Text>
          {vacations.length > 0 ? (
            <Pressable
              onPress={() => router.push("/(app)/controle")}
              hitSlop={8}
            >
              <Text style={styles.sectionAction}>Tout voir</Text>
            </Pressable>
          ) : null}
        </View>

        {vacations.length === 0 ? (
          <EmptyState
            title={noPerimetre ? "Aucune zone assignée" : "Rien à contrôler"}
            description={
              noPerimetre
                ? "Contactez votre administrateur pour vous assigner une zone."
                : "Les agents en vacation aujourd’hui apparaîtront ici."
            }
          />
        ) : (
          <View style={styles.queue}>
            {preview.map((v, index) => {
              const status = statusByVacation.get(v.id);
              const hint = status ? hintPassagesRequis(status) : null;
              return (
                <Pressable
                  key={v.id}
                  onPress={() => {
                    router.push({
                      pathname: "/(app)/controle",
                      params: { vacationId: v.id },
                    });
                  }}
                  style={({ pressed }) => [
                    styles.queueRow,
                    index === preview.length - 1 && styles.queueRowLast,
                    pressed && { opacity: 0.85 },
                  ]}
                >
                  <Avatar
                    prenom={v.agent?.prenom}
                    nom={v.agent?.nom}
                    size={40}
                  />
                  <View style={styles.queueCopy}>
                    <Text style={styles.queueName} numberOfLines={1}>
                      {v.agent?.prenom} {v.agent?.nom}
                    </Text>
                    <Text style={styles.queueMeta} numberOfLines={1}>
                      {v.site?.nom}
                      {v.poste ? ` · ${v.poste.nom}` : ""}
                      {hint ? ` · ${hint}` : ""}
                    </Text>
                  </View>
                  {status ? (
                    <ControlesPassagePill status={status} compact />
                  ) : null}
                </Pressable>
              );
            })}
            {hasMore ? (
              <Pressable
                onPress={() => router.push("/(app)/controle")}
                style={styles.moreRow}
              >
                <Text style={styles.moreText}>
                  +{vacations.length - preview.length} autres agents
                </Text>
                <Ionicons name="arrow-forward" size={16} color={colors.teal} />
              </Pressable>
            ) : null}
          </View>
        )}
      </View>
    </ScreenShell>
  );
}

const styles = StyleSheet.create({
  identity: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    marginBottom: spacing.xl,
  },
  identityText: { flex: 1, gap: 2 },
  greeting: {
    ...typography.caption,
    color: colors.inkFaint,
    textTransform: "uppercase",
    letterSpacing: 0.8,
  },
  name: {
    ...typography.title,
    color: colors.ink,
  },
  date: {
    ...typography.caption,
    color: colors.inkMuted,
    marginTop: 2,
    textTransform: "capitalize",
  },
  avatarTap: {
    borderRadius: 18,
    ...shadows.soft,
  },
  errorBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    backgroundColor: colors.dangerSoft,
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  errorText: {
    ...typography.caption,
    color: colors.danger,
    flex: 1,
  },
  mission: {
    borderRadius: radii.xl,
    padding: spacing.xl,
    gap: spacing.md,
    marginBottom: spacing.xl,
    ...shadows.card,
  },
  missionKicker: {
    ...typography.mono,
    color: "rgba(255,255,255,0.7)",
    textTransform: "uppercase",
  },
  missionRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.lg,
  },
  missionCount: {
    fontFamily: "Manrope_800ExtraBold",
    fontSize: 48,
    lineHeight: 52,
    color: colors.white,
    letterSpacing: -1.5,
    minWidth: 56,
  },
  missionCopy: { flex: 1, gap: 4 },
  missionTitle: {
    ...typography.subtitle,
    color: colors.white,
  },
  missionMeta: {
    ...typography.caption,
    color: "rgba(255,255,255,0.78)",
  },
  missionCta: {
    marginTop: spacing.xs,
    backgroundColor: colors.white,
    borderColor: colors.white,
  },
  section: {
    marginBottom: spacing.xl,
    gap: spacing.md,
  },
  sectionHead: {
    flexDirection: "row",
    alignItems: "baseline",
    justifyContent: "space-between",
  },
  sectionTitle: {
    ...typography.subtitle,
    color: colors.ink,
  },
  sectionAction: {
    ...typography.bodyStrong,
    color: colors.teal,
  },
  queue: {
    backgroundColor: colors.white,
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: "hidden",
    ...shadows.soft,
  },
  queueRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md + 2,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  queueRowLast: {
    borderBottomWidth: 0,
  },
  queueRowLocked: {
    opacity: 0.72,
    backgroundColor: colors.paperMuted,
  },
  queueCopy: { flex: 1, gap: 2 },
  queueName: { ...typography.bodyStrong, color: colors.ink },
  queueMeta: { ...typography.caption, color: colors.inkMuted },
  queueTime: {
    ...typography.mono,
    color: colors.inkFaint,
  },
  moreRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    backgroundColor: colors.tealSoft,
  },
  moreText: {
    ...typography.bodyStrong,
    color: colors.tealDark,
  },
});
