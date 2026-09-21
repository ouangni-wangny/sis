import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { colors, typography } from "@/theme/tokens";

export function Avatar({
  prenom,
  nom,
  size = 44,
}: {
  prenom?: string | null;
  nom?: string | null;
  size?: number;
}) {
  const initials = `${prenom?.[0] ?? ""}${nom?.[0] ?? ""}`.toUpperCase() || "?";

  return (
    <View
      style={[
        styles.wrap,
        {
          width: size,
          height: size,
          borderRadius: size * 0.32,
        },
      ]}
    >
      <Text style={[styles.text, { fontSize: size * 0.34 }]}>{initials}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: colors.tealSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  text: {
    ...typography.bodyStrong,
    color: colors.tealDark,
  },
});
