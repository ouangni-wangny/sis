import { useCallback, useMemo, useState, type ReactNode } from "react";
import {
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Ionicons } from "@expo/vector-icons";
import * as Haptics from "expo-haptics";
import { LinearGradient } from "expo-linear-gradient";
import { useFocusEffect } from "expo-router";
import { useAuth, getErrorMessage } from "@/auth/AuthContext";
import { anomaliesApi, vacationsApi } from "@/api/resources";
import { Button } from "@/components/Button";
import { Field } from "@/components/Field";
import { ScreenShell } from "@/components/ScreenShell";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";
import type { Site } from "@/types/api";

const TYPES: {
  value: string;
  label: string;
  hint: string;
  icon: keyof typeof Ionicons.glyphMap;
}[] = [
  {
    value: "intrusion",
    label: "Intrusion",
    hint: "Personne non autorisée",
    icon: "shield-outline",
  },
  {
    value: "incendie",
    label: "Incendie",
    hint: "Feu ou fumée",
    icon: "flame-outline",
  },
  {
    value: "technique",
    label: "Technique",
    hint: "Panne / équipement",
    icon: "construct-outline",
  },
  {
    value: "autre",
    label: "Autre",
    hint: "Situation particulière",
    icon: "ellipsis-horizontal-circle-outline",
  },
];

const GRAVITES: {
  value: string;
  label: string;
  color: string;
  soft: string;
  icon: keyof typeof Ionicons.glyphMap;
}[] = [
  {
    value: "basse",
    label: "Basse",
    color: colors.teal,
    soft: colors.tealSoft,
    icon: "remove-outline",
  },
  {
    value: "moyenne",
    label: "Moyenne",
    color: colors.warning,
    soft: colors.warningSoft,
    icon: "remove-outline",
  },
  {
    value: "haute",
    label: "Haute",
    color: "#C2410C",
    soft: "#FFEDD5",
    icon: "trending-up-outline",
  },
  {
    value: "critique",
    label: "Critique",
    color: colors.danger,
    soft: colors.dangerSoft,
    icon: "alert-outline",
  },
];

export default function AnomalieScreen() {
  const { user, token } = useAuth();
  const [sites, setSites] = useState<Site[]>([]);
  const [siteId, setSiteId] = useState<string | null>(null);
  const [type, setType] = useState("autre");
  const [gravite, setGravite] = useState("moyenne");
  const [commentaire, setCommentaire] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadingSites, setLoadingSites] = useState(false);
  const noPerimetre = (user?.agent?.perimetres?.length ?? 0) === 0;

  const selectedSite = useMemo(
    () => sites.find((s) => s.id === siteId) ?? null,
    [sites, siteId],
  );
  const selectedType = useMemo(
    () => TYPES.find((t) => t.value === type) ?? TYPES[3],
    [type],
  );
  const selectedGravite = useMemo(
    () => GRAVITES.find((g) => g.value === gravite) ?? GRAVITES[1],
    [gravite],
  );

  const loadSites = useCallback(async () => {
    if (!token) return;
    setLoadingSites(true);
    setError(null);
    try {
      const res = await vacationsApi.enPoste(token);
      const map = new Map<string, Site>();
      for (const v of res.data) {
        if (v.site) map.set(v.site.id, v.site);
      }
      const list = [...map.values()];
      setSites(list);
      if (list[0]) setSiteId((prev) => prev ?? list[0].id);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoadingSites(false);
    }
  }, [token]);

  useFocusEffect(
    useCallback(() => {
      void loadSites();
    }, [loadSites]),
  );

  async function submit() {
    if (!token || !user?.agent?.id || !siteId) {
      setError("Sélectionnez un site pour envoyer l’alerte.");
      return;
    }
    setBusy(true);
    setError(null);
    setOk(null);
    try {
      await anomaliesApi.create(token, {
        signale_par_id: user.agent.id,
        site_id: siteId,
        type,
        gravite,
        commentaire: commentaire.trim() || undefined,
        client_uuid: cryptoRandom(),
      });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setOk("Alerte transmise au back-office.");
      setCommentaire("");
      setType("autre");
      setGravite("moyenne");
    } catch (err) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScreenShell refreshing={loadingSites} onRefresh={loadSites}>
      <LinearGradient
        colors={[colors.tealDark, colors.teal]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.hero}
      >
        <View style={styles.heroIcon}>
          <Ionicons name="warning" size={22} color={colors.white} />
        </View>
        <Text style={styles.heroTitle}>Signalisation</Text>
        <Text style={styles.heroLead}>
          Décrivez l’incident : le poste de commandement est alerté immédiatement.
        </Text>
      </LinearGradient>

      {/* Récap */}
      <View style={styles.summary}>
        <SummaryRow
          icon="business-outline"
          label="Site"
          value={selectedSite?.nom ?? "Non choisi"}
        />
        <View style={styles.summaryDivider} />
        <SummaryRow
          icon={selectedType.icon}
          label="Type"
          value={selectedType.label}
        />
        <View style={styles.summaryDivider} />
        <SummaryRow
          icon={selectedGravite.icon}
          label="Gravité"
          value={selectedGravite.label}
          valueColor={selectedGravite.color}
        />
      </View>

      {/* Site */}
      <Section title="Site concerné" subtitle="Vacation en poste aujourd’hui">
        {sites.length === 0 ? (
          <View style={styles.emptyBox}>
            <Ionicons
              name="location-outline"
              size={28}
              color={colors.inkFaint}
            />
            <Text style={styles.emptyTitle}>
              {noPerimetre ? "Aucune zone assignée" : "Aucun site disponible"}
            </Text>
            <Text style={styles.emptyText}>
              {noPerimetre
                ? "Contactez votre administrateur pour vous assigner une zone."
                : "Vous devez être en vacation active pour signaler une anomalie."}
            </Text>
          </View>
        ) : (
          <View style={styles.siteList}>
            {sites.map((site) => {
              const active = siteId === site.id;
              return (
                <Pressable
                  key={site.id}
                  onPress={() => {
                    setSiteId(site.id);
                    void Haptics.selectionAsync();
                  }}
                  style={[styles.siteCard, active && styles.siteCardActive]}
                >
                  <View
                    style={[
                      styles.siteIcon,
                      active && styles.siteIconActive,
                    ]}
                  >
                    <Ionicons
                      name="business"
                      size={18}
                      color={active ? colors.white : colors.teal}
                    />
                  </View>
                  <View style={styles.siteText}>
                    <Text
                      style={[
                        styles.siteName,
                        active && styles.siteNameActive,
                      ]}
                      numberOfLines={1}
                    >
                      {site.nom}
                    </Text>
                    {site.adresse ? (
                      <Text style={styles.siteAddr} numberOfLines={1}>
                        {site.adresse}
                      </Text>
                    ) : null}
                  </View>
                  {active ? (
                    <Ionicons
                      name="checkmark-circle"
                      size={22}
                      color={colors.teal}
                    />
                  ) : (
                    <View style={styles.radio} />
                  )}
                </Pressable>
              );
            })}
          </View>
        )}
      </Section>

      {/* Type */}
      <Section title="Type d’anomalie" subtitle="Choisissez la catégorie">
        <View style={styles.typeGrid}>
          {TYPES.map((t) => {
            const active = type === t.value;
            return (
              <Pressable
                key={t.value}
                onPress={() => {
                  setType(t.value);
                  void Haptics.selectionAsync();
                }}
                style={[styles.typeCard, active && styles.typeCardActive]}
              >
                <View
                  style={[
                    styles.typeIcon,
                    active && styles.typeIconActive,
                  ]}
                >
                  <Ionicons
                    name={t.icon}
                    size={20}
                    color={active ? colors.white : colors.teal}
                  />
                </View>
                <Text
                  style={[styles.typeLabel, active && styles.typeLabelActive]}
                >
                  {t.label}
                </Text>
                <Text
                  style={[styles.typeHint, active && styles.typeHintActive]}
                  numberOfLines={2}
                >
                  {t.hint}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </Section>

      {/* Gravité */}
      <Section title="Niveau de gravité" subtitle="Priorité de traitement">
        <View style={styles.graviteRow}>
          {GRAVITES.map((g) => {
            const active = gravite === g.value;
            return (
              <Pressable
                key={g.value}
                onPress={() => {
                  setGravite(g.value);
                  void Haptics.selectionAsync();
                }}
                style={[
                  styles.graviteChip,
                  { backgroundColor: active ? g.color : g.soft },
                  active && styles.graviteChipActive,
                ]}
              >
                <Text
                  style={[
                    styles.graviteText,
                    { color: active ? colors.white : g.color },
                  ]}
                >
                  {g.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </Section>

      {/* Description */}
      <Section title="Description" subtitle="Optionnel mais recommandé">
        <Field
          label="Que s’est-il passé ?"
          value={commentaire}
          onChangeText={setCommentaire}
          placeholder="Heure, lieu précis, personnes impliquées…"
          multiline
          style={styles.textarea}
        />
      </Section>

      {error ? (
        <View style={styles.bannerError}>
          <Ionicons name="alert-circle" size={18} color={colors.danger} />
          <Text style={styles.bannerErrorText}>{error}</Text>
        </View>
      ) : null}
      {ok ? (
        <View style={styles.bannerOk}>
          <Ionicons name="checkmark-circle" size={18} color={colors.teal} />
          <Text style={styles.bannerOkText}>{ok}</Text>
        </View>
      ) : null}

      <Button
        label="Envoyer l’alerte"
        onPress={submit}
        loading={busy}
        disabled={!siteId || sites.length === 0}
        variant="danger"
        style={styles.submit}
      />
      <Text style={styles.footerHint}>
        L’alerte est visible immédiatement dans le module Anomalies du back-office.
      </Text>
    </ScreenShell>
  );
}

function Section({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {subtitle ? <Text style={styles.sectionSub}>{subtitle}</Text> : null}
      <View style={styles.sectionBody}>{children}</View>
    </View>
  );
}

function SummaryRow({
  icon,
  label,
  value,
  valueColor,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  valueColor?: string;
}) {
  return (
    <View style={styles.summaryRow}>
      <View style={styles.summaryLeft}>
        <Ionicons name={icon} size={16} color={colors.inkFaint} />
        <Text style={styles.summaryLabel}>{label}</Text>
      </View>
      <Text
        style={[styles.summaryValue, valueColor ? { color: valueColor } : null]}
        numberOfLines={1}
      >
        {value}
      </Text>
    </View>
  );
}

function cryptoRandom() {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: radii.lg,
    padding: spacing.xl,
    marginBottom: spacing.lg,
    ...shadows.card,
  },
  heroIcon: {
    width: 40,
    height: 40,
    borderRadius: radii.sm,
    backgroundColor: "rgba(255,255,255,0.18)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: spacing.md,
  },
  heroTitle: {
    ...typography.title,
    color: colors.white,
    marginBottom: spacing.xs,
  },
  heroLead: {
    ...typography.body,
    color: "rgba(255,255,255,0.85)",
  },

  summary: {
    backgroundColor: colors.white,
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    marginBottom: spacing.xl,
    ...shadows.soft,
  },
  summaryRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: spacing.md,
    paddingVertical: spacing.md,
  },
  summaryLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
  },
  summaryLabel: {
    ...typography.caption,
    color: colors.inkFaint,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  summaryValue: {
    ...typography.bodyStrong,
    color: colors.ink,
    flexShrink: 1,
    textAlign: "right",
  },
  summaryDivider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: colors.border,
  },

  section: {
    marginBottom: spacing.xl,
  },
  sectionTitle: {
    ...typography.subtitle,
    color: colors.ink,
  },
  sectionSub: {
    ...typography.caption,
    color: colors.inkFaint,
    marginTop: 2,
    marginBottom: spacing.md,
  },
  sectionBody: {
    marginTop: spacing.sm,
  },

  emptyBox: {
    alignItems: "center",
    gap: spacing.sm,
    paddingVertical: spacing.xl,
    paddingHorizontal: spacing.lg,
    backgroundColor: colors.white,
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.border,
  },
  emptyTitle: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  emptyText: {
    ...typography.caption,
    color: colors.inkMuted,
    textAlign: "center",
  },

  siteList: {
    gap: spacing.sm,
  },
  siteCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radii.md,
    borderWidth: 1.5,
    borderColor: colors.border,
    padding: spacing.md,
  },
  siteCardActive: {
    borderColor: colors.teal,
    backgroundColor: colors.tealSoft,
  },
  siteIcon: {
    width: 40,
    height: 40,
    borderRadius: radii.sm,
    backgroundColor: colors.tealSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  siteIconActive: {
    backgroundColor: colors.teal,
  },
  siteText: {
    flex: 1,
    minWidth: 0,
  },
  siteName: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  siteNameActive: {
    color: colors.tealDark,
  },
  siteAddr: {
    ...typography.caption,
    color: colors.inkFaint,
    marginTop: 2,
  },
  radio: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1.5,
    borderColor: colors.border,
  },

  typeGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: spacing.sm,
  },
  typeCard: {
    width: "48%",
    flexGrow: 1,
    minWidth: "46%",
    backgroundColor: colors.white,
    borderRadius: radii.md,
    borderWidth: 1.5,
    borderColor: colors.border,
    padding: spacing.md,
    gap: spacing.xs,
  },
  typeCardActive: {
    borderColor: colors.teal,
    backgroundColor: colors.tealSoft,
  },
  typeIcon: {
    width: 36,
    height: 36,
    borderRadius: radii.sm,
    backgroundColor: colors.tealSoft,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: spacing.xs,
  },
  typeIconActive: {
    backgroundColor: colors.teal,
  },
  typeLabel: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  typeLabelActive: {
    color: colors.tealDark,
  },
  typeHint: {
    ...typography.caption,
    color: colors.inkFaint,
  },
  typeHintActive: {
    color: colors.inkMuted,
  },

  graviteRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: spacing.sm,
  },
  graviteChip: {
    flexGrow: 1,
    minWidth: "22%",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.sm,
    borderRadius: radii.md,
  },
  graviteChipActive: {
    ...shadows.soft,
  },
  graviteText: {
    ...typography.caption,
    fontFamily: "Manrope_700Bold",
    letterSpacing: 0.3,
  },

  textarea: {
    minHeight: 110,
    textAlignVertical: "top",
    paddingTop: spacing.md,
  },

  bannerError: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    backgroundColor: colors.dangerSoft,
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.md,
  },
  bannerErrorText: {
    ...typography.caption,
    color: colors.danger,
    flex: 1,
  },
  bannerOk: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    backgroundColor: colors.tealSoft,
    borderRadius: radii.md,
    padding: spacing.md,
    marginBottom: spacing.md,
  },
  bannerOkText: {
    ...typography.caption,
    color: colors.tealDark,
    flex: 1,
  },

  submit: {
    marginTop: spacing.sm,
  },
  footerHint: {
    ...typography.caption,
    color: colors.inkFaint,
    textAlign: "center",
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
});
