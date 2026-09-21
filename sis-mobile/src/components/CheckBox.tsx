import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { colors, radii, spacing, typography } from "@/theme/tokens";

type Props = {
  checked: boolean;
  onChange: (next: boolean) => void;
  label: string;
  description?: string;
  tone?: "teal" | "danger";
};

export function CheckBox({
  checked,
  onChange,
  label,
  description,
  tone = "teal",
}: Props) {
  const accent = tone === "danger" ? colors.danger : colors.teal;
  const soft = tone === "danger" ? colors.dangerSoft : colors.tealSoft;

  return (
    <Pressable
      accessibilityRole="checkbox"
      accessibilityState={{ checked }}
      onPress={() => onChange(!checked)}
      style={({ pressed }) => [
        styles.row,
        checked && { backgroundColor: soft, borderColor: accent },
        pressed && styles.pressed,
      ]}
    >
      <View
        style={[
          styles.box,
          { borderColor: checked ? accent : colors.border },
          checked && { backgroundColor: accent },
        ]}
      >
        {checked ? (
          <Ionicons name="checkmark" size={16} color={colors.white} />
        ) : null}
      </View>
      <View style={styles.copy}>
        <Text style={[styles.label, checked && { color: colors.ink }]}>
          {label}
        </Text>
        {description ? (
          <Text style={styles.description}>{description}</Text>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: spacing.md,
    padding: spacing.lg,
    borderRadius: radii.md,
    borderWidth: 1.5,
    borderColor: colors.border,
    backgroundColor: colors.white,
  },
  pressed: { opacity: 0.92, transform: [{ scale: 0.995 }] },
  box: {
    width: 24,
    height: 24,
    borderRadius: 8,
    borderWidth: 1.5,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 1,
  },
  copy: { flex: 1, gap: 4 },
  label: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  description: {
    ...typography.caption,
    color: colors.inkMuted,
    lineHeight: 18,
  },
});
