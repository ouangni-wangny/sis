import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { PassageStatus } from "@/lib/controlePassages";
import { labelBadgeControle } from "@/lib/controlePassages";
import { colors, radii, spacing, typography } from "@/theme/tokens";

export type PresenceStatus = "a_faire" | "present" | "absent" | "enregistre";

const CONFIG: Record<
  PresenceStatus,
  { label: string; bg: string; fg: string; icon: keyof typeof Ionicons.glyphMap }
> = {
  a_faire: {
    label: "À faire",
    bg: colors.paperMuted,
    fg: colors.inkMuted,
    icon: "time-outline",
  },
  present: {
    label: "Présent",
    bg: colors.tealSoft,
    fg: colors.tealDark,
    icon: "checkmark-circle",
  },
  absent: {
    label: "Absent",
    bg: colors.dangerSoft,
    fg: colors.danger,
    icon: "close-circle",
  },
  enregistre: {
    label: "Contrôlé",
    bg: colors.tealSoft,
    fg: colors.tealDark,
    icon: "shield-checkmark",
  },
};

export function StatusPill({
  status,
  compact = false,
}: {
  status: PresenceStatus;
  compact?: boolean;
}) {
  const cfg = CONFIG[status];
  return (
    <View style={[styles.pill, { backgroundColor: cfg.bg }]}>
      <Ionicons name={cfg.icon} size={compact ? 12 : 14} color={cfg.fg} />
      <Text style={[styles.text, { color: cfg.fg, fontSize: compact ? 11 : 12 }]}>
        {cfg.label}
      </Text>
    </View>
  );
}

/** Badge dérivé des passages matin / soir (24h vs quarts). */
export function ControlesPassagePill({
  status,
  compact = false,
}: {
  status: PassageStatus;
  compact?: boolean;
}) {
  const badge = labelBadgeControle(status);
  const tone =
    badge.tone === "ok"
      ? { bg: colors.tealSoft, fg: colors.tealDark, icon: "checkmark-circle" as const }
      : badge.tone === "danger"
        ? { bg: colors.dangerSoft, fg: colors.danger, icon: "close-circle" as const }
        : badge.tone === "partial"
          ? { bg: "#FEF3C7", fg: "#92400E", icon: "time-outline" as const }
          : {
              bg: colors.paperMuted,
              fg: colors.inkMuted,
              icon: "time-outline" as const,
            };

  return (
    <View style={[styles.pill, { backgroundColor: tone.bg }]}>
      <Ionicons name={tone.icon} size={compact ? 12 : 14} color={tone.fg} />
      <Text style={[styles.text, { color: tone.fg, fontSize: compact ? 11 : 12 }]}>
        {badge.text}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    alignSelf: "flex-start",
    paddingHorizontal: spacing.sm + 2,
    paddingVertical: spacing.xs,
    borderRadius: radii.full,
  },
  text: {
    ...typography.caption,
    fontFamily: "Manrope_600SemiBold",
  },
});
