import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { colors, radii, spacing, typography } from "@/theme/tokens";

const STATUT_STYLES: Record<string, { bg: string; fg: string }> = {
  planifiee: { bg: "#E8F0EE", fg: colors.tealDark },
  en_cours: { bg: "#E6F4F1", fg: colors.teal },
  terminee: { bg: colors.paperMuted, fg: colors.inkMuted },
  annulee: { bg: "#FCEBEA", fg: colors.danger },
  disponible: { bg: "#E6F4F1", fg: colors.teal },
};

export function Badge({ label }: { label: string }) {
  const tone = STATUT_STYLES[label] ?? { bg: colors.paperMuted, fg: colors.inkMuted };

  return (
    <View style={[styles.badge, { backgroundColor: tone.bg }]}>
      <Text style={[styles.text, { color: tone.fg }]}>{label.replaceAll("_", " ")}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: radii.sm,
  },
  text: {
    ...typography.caption,
    textTransform: "capitalize",
  },
});
