import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { colors, radii, spacing, typography } from "@/theme/tokens";

export type PresenceValue = "present" | "absent" | null;

type Props = {
  value: PresenceValue;
  onChange: (value: PresenceValue) => void;
};

export function PresenceToggle({ value, onChange }: Props) {
  return (
    <View style={styles.wrap}>
      <Option
        active={value === "present"}
        icon="checkmark-circle"
        title="Présent"
        subtitle="Agent à son poste"
        tone="teal"
        onPress={() => onChange(value === "present" ? null : "present")}
      />
      <Option
        active={value === "absent"}
        icon="close-circle"
        title="Absent"
        subtitle="Hors poste / introuvable"
        tone="danger"
        onPress={() => onChange(value === "absent" ? null : "absent")}
      />
    </View>
  );
}

function Option({
  active,
  icon,
  title,
  subtitle,
  tone,
  onPress,
}: {
  active: boolean;
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  subtitle: string;
  tone: "teal" | "danger";
  onPress: () => void;
}) {
  const accent = tone === "danger" ? colors.danger : colors.teal;
  const soft = tone === "danger" ? colors.dangerSoft : colors.tealSoft;

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.option,
        active && { borderColor: accent, backgroundColor: soft },
        pressed && { opacity: 0.9 },
      ]}
    >
      <View style={[styles.iconWrap, active && { backgroundColor: accent }]}>
        <Ionicons
          name={icon}
          size={20}
          color={active ? colors.white : accent}
        />
      </View>
      <Text style={[styles.title, active && { color: colors.ink }]}>{title}</Text>
      <Text style={styles.subtitle}>{subtitle}</Text>
      <View
        style={[
          styles.check,
          active && { borderColor: accent, backgroundColor: accent },
        ]}
      >
        {active ? (
          <Ionicons name="checkmark" size={12} color={colors.white} />
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: "row",
    gap: spacing.md,
  },
  option: {
    flex: 1,
    borderRadius: radii.lg,
    borderWidth: 1.5,
    borderColor: colors.border,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.sm,
    minHeight: 128,
  },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: radii.sm,
    backgroundColor: colors.paperMuted,
    alignItems: "center",
    justifyContent: "center",
  },
  title: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  subtitle: {
    ...typography.caption,
    color: colors.inkMuted,
    lineHeight: 16,
  },
  check: {
    position: "absolute",
    top: spacing.md,
    right: spacing.md,
    width: 20,
    height: 20,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: colors.border,
    alignItems: "center",
    justifyContent: "center",
  },
});
