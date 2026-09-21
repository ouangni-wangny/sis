import { StyleSheet, Text, View } from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import { API_URL } from "@/api/config";
import { useAuth } from "@/auth/AuthContext";
import { Avatar } from "@/components/Avatar";
import { Badge } from "@/components/Badge";
import { Button } from "@/components/Button";
import { ScreenShell } from "@/components/ScreenShell";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";

export default function ProfilScreen() {
  const { user, logout } = useAuth();
  const agent = user?.agent;

  return (
    <ScreenShell>
      <LinearGradient
        colors={[colors.tealDark, colors.teal]}
        style={styles.hero}
      >
        <Avatar prenom={user?.prenom} nom={user?.nom} size={64} />
        <Text style={styles.name}>
          {user?.prenom} {user?.nom}
        </Text>
        <Text style={styles.mono}>{user?.matricule}</Text>
        {agent ? <Badge label={agent.type} /> : null}
      </LinearGradient>

      <View style={styles.card}>
        <Text style={styles.label}>Statut</Text>
        <Text style={styles.value}>{agent?.statut ?? "—"}</Text>
        <Text style={styles.label}>Rôles</Text>
        <Text style={styles.value}>
          {(user?.roles ?? []).join(", ") || "—"}
        </Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>API connectée</Text>
        <Text style={styles.monoSmall}>{API_URL}</Text>
      </View>

      <Button
        label="Se déconnecter"
        variant="danger"
        onPress={() => void logout()}
      />
    </ScreenShell>
  );
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: radii.xl,
    padding: spacing.xl,
    alignItems: "center",
    gap: spacing.sm,
    marginBottom: spacing.lg,
    ...shadows.card,
  },
  name: {
    ...typography.title,
    color: colors.white,
    textAlign: "center",
  },
  mono: {
    ...typography.mono,
    color: "rgba(255,255,255,0.8)",
  },
  card: {
    backgroundColor: colors.white,
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.xl,
    gap: spacing.sm,
    marginBottom: spacing.lg,
    ...shadows.soft,
  },
  label: {
    ...typography.caption,
    color: colors.inkFaint,
    textTransform: "uppercase",
    letterSpacing: 0.6,
    marginTop: spacing.sm,
  },
  value: {
    ...typography.bodyStrong,
    color: colors.ink,
  },
  monoSmall: {
    ...typography.mono,
    fontSize: 11,
    color: colors.inkMuted,
  },
});
