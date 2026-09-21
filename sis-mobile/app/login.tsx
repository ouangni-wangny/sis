import { useState } from "react";
import {
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import { Redirect, router } from "expo-router";
import { API_URL } from "@/api/config";
import { getErrorMessage, useAuth } from "@/auth/AuthContext";
import { Button } from "@/components/Button";
import { Field } from "@/components/Field";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";

export default function LoginScreen() {
  const { user, login, loading } = useAuth();
  const [matricule, setMatricule] = useState("");
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (!loading && user) return <Redirect href="/(app)" />;

  async function onSubmit() {
    setError(null);
    setSubmitting(true);
    try {
      await login(matricule, pin);
      router.replace("/(app)");
    } catch (err) {
      const msg = getErrorMessage(err);
      const network =
        /network request failed|failed to fetch|network error/i.test(msg);
      setError(
        network
          ? `Impossible de joindre l’API (${API_URL}). Vérifiez le Wi‑Fi et que Laravel tourne avec --host=0.0.0.0.`
          : msg,
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <LinearGradient
      colors={[colors.tealDark, colors.teal, colors.canvasBottom]}
      locations={[0, 0.45, 1]}
      style={styles.gradient}
    >
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        style={styles.container}
      >
        <View style={styles.hero}>
          <View style={styles.logoMark}>
            <Text style={styles.logoMarkText}>SIS</Text>
          </View>
          <Text style={styles.brand}>S.I.S Terrain</Text>
          <Text style={styles.tagline}>Contrôle de présence · Côte d’Ivoire</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Connexion rondier</Text>
          <Text style={styles.cardLead}>
            Identifiez-vous avec votre matricule et votre PIN.
          </Text>
          <Field
            label="Matricule"
            autoCapitalize="characters"
            autoCorrect={false}
            value={matricule}
            onChangeText={setMatricule}
            placeholder="RD-2001"
          />
          <Field
            label="PIN"
            secureTextEntry
            keyboardType="number-pad"
            maxLength={6}
            value={pin}
            onChangeText={setPin}
            placeholder="••••"
          />
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <Button label="Entrer" onPress={onSubmit} loading={submitting} />
        </View>
      </KeyboardAvoidingView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  container: {
    flex: 1,
    justifyContent: "center",
    padding: spacing.xl,
    gap: spacing.xl,
  },
  hero: { gap: spacing.sm, alignItems: "flex-start" },
  logoMark: {
    width: 52,
    height: 52,
    borderRadius: 16,
    backgroundColor: "rgba(255,255,255,0.18)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: spacing.sm,
  },
  logoMarkText: {
    ...typography.bodyStrong,
    color: colors.white,
    letterSpacing: 1,
  },
  brand: {
    ...typography.brand,
    color: colors.white,
  },
  tagline: {
    ...typography.body,
    color: "rgba(255,255,255,0.82)",
  },
  card: {
    backgroundColor: colors.white,
    borderRadius: radii.xl,
    padding: spacing.xl,
    gap: spacing.lg,
    ...shadows.card,
  },
  cardTitle: {
    ...typography.subtitle,
    color: colors.ink,
  },
  cardLead: {
    ...typography.caption,
    color: colors.inkMuted,
    marginTop: -8,
  },
  error: {
    ...typography.caption,
    color: colors.danger,
  },
});
