import React from "react";
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
} from "react-native";
import { colors, spacing } from "@/theme/tokens";

export function ScreenShell({
  children,
  padded = true,
  refreshing,
  onRefresh,
}: {
  children: React.ReactNode;
  padded?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
}) {
  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={[styles.content, padded && styles.padded]}
      keyboardShouldPersistTaps="handled"
      refreshControl={
        onRefresh ? (
          <RefreshControl
            refreshing={Boolean(refreshing)}
            onRefresh={onRefresh}
            tintColor={colors.teal}
            colors={[colors.teal]}
          />
        ) : undefined
      }
    >
      <View>{children}</View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flex: 1,
    backgroundColor: colors.paper,
  },
  content: {
    flexGrow: 1,
  },
  padded: {
    padding: spacing.lg,
    paddingBottom: spacing.xxl + 8,
  },
});
