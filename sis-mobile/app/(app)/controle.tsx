import { useCallback, useEffect, useRef, useState } from "react";
import {
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import * as Haptics from "expo-haptics";
import { Ionicons } from "@expo/vector-icons";
import { useFocusEffect, useLocalSearchParams, router } from "expo-router";
import { getErrorMessage, useAuth } from "@/auth/AuthContext";
import { controlesApi, vacationsApi } from "@/api/resources";
import { Avatar } from "@/components/Avatar";
import { Button } from "@/components/Button";
import { CheckBox } from "@/components/CheckBox";
import { EmptyState } from "@/components/EmptyState";
import { Field } from "@/components/Field";
import {
  PresenceToggle,
  type PresenceValue,
} from "@/components/PresenceToggle";
import { ControlesPassagePill } from "@/components/StatusPill";
import { StepFooter } from "@/components/StepFooter";
import {
  hintPassagesRequis,
  indexControlesParPassage,
  statusControleVacation,
  type PassageStatus,
} from "@/lib/controlePassages";
import { getCurrentCoords } from "@/lib/location";
import { prepareControlPhoto } from "@/lib/prepareControlPhoto";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";
import type { ControleResultat, Vacation } from "@/types/api";

type Step = "list" | "photo" | "confirm" | "done";

function uuid() {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

const STEPS: Step[] = ["list", "photo", "confirm", "done"];

export default function ControlesScreen() {
  const { user, token } = useAuth();
  const { vacationId } = useLocalSearchParams<{ vacationId?: string }>();
  const cameraRef = useRef<CameraView>(null);
  const [permission, requestPermission] = useCameraPermissions();
  const [cameraOpen, setCameraOpen] = useState(false);
  const [vacations, setVacations] = useState<Vacation[]>([]);
  const [step, setStep] = useState<Step>("list");
  const [selected, setSelected] = useState<Vacation | null>(null);
  const openedVacationId = useRef<string | null>(null);
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [photoBase64, setPhotoBase64] = useState<string | null>(null);
  const [presence, setPresence] = useState<PresenceValue>(null);
  const [confirmed, setConfirmed] = useState(false);
  const [showNote, setShowNote] = useState(false);
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [okMessage, setOkMessage] = useState<string | null>(null);
  const [doneResultat, setDoneResultat] = useState<ControleResultat | null>(null);
  const [statusByVacation, setStatusByVacation] = useState(
    () => new Map<string, PassageStatus>(),
  );
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const noPerimetre = (user?.agent?.perimetres?.length ?? 0) === 0;

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
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
      setLoading(false);
    }
  }, [token]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  function resetForm() {
    setPhotoUri(null);
    setPhotoBase64(null);
    setPresence(null);
    setConfirmed(false);
    setShowNote(false);
    setNote("");
  }

  function backToList() {
    resetForm();
    setSelected(null);
    setError(null);
    setStep("list");
    openedVacationId.current = null;
  }

  function selectAgent(v: Vacation) {
    const status = statusByVacation.get(v.id);
    // Complet : on laisse quand même ouvrir pour un éventuel recontrôle
    // (le serveur applique le délai minimum). Partiel 24h : 2ᵉ passage OK.
    if (status?.complet) {
      // Inform without locking — same spirit as web terrain.
    }
    void Haptics.selectionAsync();
    setSelected(v);
    resetForm();
    setOkMessage(null);
    setError(null);
    setStep("photo");
  }

  // Depuis l’accueil : ouvrir directement l’agent choisi (pas toute la liste).
  useEffect(() => {
    if (!vacationId || loading || vacations.length === 0) return;
    if (openedVacationId.current === vacationId) return;
    const match = vacations.find((v) => v.id === vacationId);
    if (!match) return;
    openedVacationId.current = vacationId;
    router.setParams({ vacationId: undefined });
    selectAgent(match);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vacationId, vacations, loading]);

  async function openCamera() {
    if (!permission?.granted) {
      const res = await requestPermission();
      if (!res.granted) {
        setError("Autorisez la caméra pour photographier l’agent.");
        return;
      }
    }
    setCameraOpen(true);
    setError(null);
  }

  async function capture() {
    try {
      const photo = await cameraRef.current?.takePictureAsync({
        quality: 0.6,
        skipProcessing: true,
      });
      if (!photo?.uri) {
        setError("Capture photo échouée. Réessayez.");
        return;
      }
      // Compression client → payload léger pour l’API (évite post_max 2M).
      const prepared = await prepareControlPhoto(photo.uri);
      setPhotoUri(prepared.uri);
      setPhotoBase64(prepared.base64);
      setCameraOpen(false);
      setError(null);
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  async function submit() {
    if (!token || !user?.agent?.id || !selected || !photoBase64 || !presence) {
      setError("Complétez toutes les étapes.");
      return;
    }
    if (!confirmed) {
      setError("Cochez la confirmation avant de valider.");
      return;
    }

    setBusy(true);
    setError(null);
    try {
      const coords = await getCurrentCoords();
      const auto =
        presence === "present"
          ? "Présence confirmée au poste"
          : "Absence constatée — agent hors poste";
      const created = await controlesApi.createPresence(token, {
        agent_id: user.agent.id,
        controle_agent_id: selected.agent_id,
        site_id: selected.site_id,
        poste_id: selected.poste_id,
        latitude: coords.latitude,
        longitude: coords.longitude,
        commentaire: note.trim() ? `${auto}. ${note.trim()}` : auto,
        client_uuid: uuid(),
        photo_base64: photoBase64,
      });
      const resultat =
        created.data.resultat ??
        (presence === "present" ? "present" : "absent");
      const msg =
        resultat === "absent"
          ? `Absence enregistrée pour ${selected.agent?.prenom} ${selected.agent?.nom}`
          : `Présence enregistrée pour ${selected.agent?.prenom} ${selected.agent?.nom}`;
      setDoneResultat(resultat);
      setOkMessage(msg);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setStep("done");
      void load();
    } catch (err) {
      setError(getErrorMessage(err));
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
    } finally {
      setBusy(false);
    }
  }

  const stepIndex = Math.max(0, STEPS.indexOf(step));
  const progressLabel =
    step === "list"
      ? "Choisir un agent"
      : step === "photo"
        ? "Étape 1 / 2 · Photo"
        : step === "confirm"
          ? "Étape 2 / 2 · Validation"
          : "Terminé";

  return (
    <View style={styles.root}>
      {step !== "list" && step !== "done" ? (
        <AgentBar
          vacation={selected}
          progress={progressLabel}
          onBack={() => {
            if (step === "confirm") {
              setStep("photo");
              setError(null);
            } else {
              backToList();
            }
          }}
        />
      ) : null}

      {step === "list" ? (
        <ScrollView
          style={styles.flex}
          contentContainerStyle={styles.listContent}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.listHeader}>
            <Text style={styles.listTitle}>Agents en poste</Text>
            <Text style={styles.listLead}>
              Touchez un agent pour démarrer le contrôle — une étape à la fois.
            </Text>
            <View style={styles.countPill}>
              <Ionicons name="people" size={14} color={colors.teal} />
              <Text style={styles.countText}>
                {vacations.filter((v) => !statusByVacation.get(v.id)?.complet).length}{" "}
                restants ·{" "}
                {vacations.filter((v) => statusByVacation.get(v.id)?.complet).length}{" "}
                complets
              </Text>
            </View>
          </View>

          {error && step === "list" ? (
            <Banner tone="error" text={error} />
          ) : null}

          {vacations.length === 0 && !loading ? (
            <EmptyState
              title={noPerimetre ? "Aucune zone assignée" : "Aucun agent en poste"}
              description={
                noPerimetre
                  ? "Contactez votre administrateur pour vous assigner une zone."
                  : "Tirez pour actualiser ou revenez plus tard."
              }
            />
          ) : (
            <View style={styles.list}>
              {vacations.map((v) => {
                const status = statusByVacation.get(v.id);
                const hint = status ? hintPassagesRequis(status) : null;
                return (
                  <Pressable
                    key={v.id}
                    onPress={() => selectAgent(v)}
                    style={({ pressed }) => [
                      styles.agentCard,
                      pressed && {
                        opacity: 0.92,
                        transform: [{ scale: 0.99 }],
                      },
                    ]}
                  >
                    <Avatar prenom={v.agent?.prenom} nom={v.agent?.nom} />
                    <View style={styles.agentCopy}>
                      <Text style={styles.agentName}>
                        {v.agent?.prenom} {v.agent?.nom}
                      </Text>
                      <Text style={styles.agentMeta}>
                        {v.site?.nom}
                        {v.poste ? ` · ${v.poste.nom}` : ""}
                      </Text>
                      {hint ? (
                        <Text style={styles.agentMeta}>{hint}</Text>
                      ) : null}
                      <View style={styles.statusRow}>
                        {status ? (
                          <ControlesPassagePill status={status} compact />
                        ) : null}
                        {status?.dernierEffectueAt ? (
                          <Text style={styles.agentMeta}>
                            {new Date(status.dernierEffectueAt).toLocaleTimeString(
                              "fr-FR",
                              { hour: "2-digit", minute: "2-digit" },
                            )}
                          </Text>
                        ) : null}
                      </View>
                    </View>
                    <View style={styles.chevron}>
                      <Ionicons
                        name="chevron-forward"
                        size={18}
                        color={colors.teal}
                      />
                    </View>
                  </Pressable>
                );
              })}
            </View>
          )}

          <Pressable onPress={() => void load()} style={styles.refreshRow}>
            <Ionicons name="refresh" size={16} color={colors.inkMuted} />
            <Text style={styles.refreshText}>
              {loading ? "Actualisation…" : "Actualiser la liste"}
            </Text>
          </Pressable>
        </ScrollView>
      ) : null}

      {step === "photo" && selected ? (
        <>
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.stepContent}
            keyboardShouldPersistTaps="handled"
          >
            <Text style={styles.stepTitle}>Photographier l’agent</Text>
            <Text style={styles.stepLead}>
              Preuve visuelle obligatoire avant de valider la présence.
            </Text>

            <Pressable onPress={openCamera} style={styles.photoBox}>
              {photoUri ? (
                <Image source={{ uri: photoUri }} style={styles.photo} />
              ) : (
                <View style={styles.photoEmpty}>
                  <View style={styles.photoIcon}>
                    <Ionicons name="camera" size={28} color={colors.teal} />
                  </View>
                  <Text style={styles.photoTitle}>Ouvrir la caméra</Text>
                  <Text style={styles.photoHint}>Cadrez le visage / le poste</Text>
                </View>
              )}
              {photoUri ? (
                <View style={styles.photoBadge}>
                  <Ionicons name="refresh" size={14} color={colors.white} />
                  <Text style={styles.photoBadgeText}>Reprendre</Text>
                </View>
              ) : null}
            </Pressable>

            {error ? <Banner tone="error" text={error} /> : null}
          </ScrollView>
          <StepFooter
            secondaryLabel="Changer d’agent"
            onSecondary={backToList}
            primaryLabel={photoBase64 ? "Continuer" : "Prendre la photo"}
            onPrimary={() => {
              if (!photoBase64) {
                void openCamera();
                return;
              }
              setError(null);
              setStep("confirm");
            }}
          />
        </>
      ) : null}

      {step === "confirm" && selected ? (
        <>
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.stepContent}
            keyboardShouldPersistTaps="handled"
          >
            <Text style={styles.stepTitle}>Confirmer la présence</Text>
            <Text style={styles.stepLead}>
              Indiquez le statut, puis validez — GPS enregistré automatiquement.
            </Text>

            {photoUri ? (
              <View style={styles.thumbRow}>
                <Image source={{ uri: photoUri }} style={styles.thumb} />
                <Pressable onPress={() => setStep("photo")} hitSlop={8}>
                  <Text style={styles.link}>Modifier la photo</Text>
                </Pressable>
              </View>
            ) : null}

            <Text style={styles.fieldLabel}>Statut</Text>
            <PresenceToggle
              value={presence}
              onChange={(v) => {
                setPresence(v);
                setConfirmed(false);
              }}
            />

            <Text style={[styles.fieldLabel, { marginTop: spacing.lg }]}>
              Confirmation
            </Text>
            <CheckBox
              checked={confirmed}
              onChange={setConfirmed}
              tone={presence === "absent" ? "danger" : "teal"}
              label={
                presence === "absent"
                  ? "Je confirme que l’agent est absent"
                  : "Je confirme que l’agent est présent"
              }
              description="Horodatage + position GPS du rondier."
            />

            <Pressable
              onPress={() => setShowNote((s) => !s)}
              style={styles.noteToggle}
            >
              <Ionicons
                name={showNote ? "chevron-up" : "chevron-down"}
                size={16}
                color={colors.inkMuted}
              />
              <Text style={styles.noteToggleText}>
                {showNote ? "Masquer la note" : "Ajouter une note (optionnel)"}
              </Text>
            </Pressable>
            {showNote ? (
              <Field
                label="Note"
                value={note}
                onChangeText={setNote}
                placeholder="Observation complémentaire…"
              />
            ) : null}

            {error ? <Banner tone="error" text={error} /> : null}
          </ScrollView>
          <StepFooter
            secondaryLabel="Retour photo"
            onSecondary={() => {
              setStep("photo");
              setError(null);
            }}
            primaryLabel="Valider le contrôle"
            onPrimary={submit}
            primaryLoading={busy}
            primaryDisabled={!presence || !confirmed || !photoBase64}
          />
        </>
      ) : null}

      {step === "done" ? (
        <View style={styles.doneWrap}>
          <View
            style={[
              styles.doneIcon,
              doneResultat === "absent" && { backgroundColor: colors.danger },
            ]}
          >
            <Ionicons
              name={doneResultat === "absent" ? "close" : "checkmark"}
              size={36}
              color={colors.white}
            />
          </View>
          <Text style={styles.doneTitle}>Contrôle bien enregistré</Text>
          {doneResultat ? (
            <StatusPill
              status={doneResultat === "absent" ? "absent" : "present"}
            />
          ) : null}
          <Text style={styles.doneLead}>{okMessage}</Text>
          <Text style={styles.doneHint}>
            Le statut apparaît maintenant sur la liste des agents.
          </Text>
          <Button
            label="Retour à la liste"
            onPress={backToList}
            style={{ marginTop: spacing.xl, alignSelf: "stretch" }}
          />
        </View>
      ) : null}

      <Modal visible={cameraOpen} animationType="slide">
        <View style={styles.cameraWrap}>
          <CameraView ref={cameraRef} style={styles.camera} facing="back" />
          <View style={styles.cameraFrame} />
          <View style={styles.cameraActions}>
            <Text style={styles.cameraHint}>
              {selected?.agent?.prenom} {selected?.agent?.nom}
            </Text>
            <Button
              label="Annuler"
              variant="secondary"
              onPress={() => setCameraOpen(false)}
            />
            <Button label="Capturer" onPress={capture} />
          </View>
        </View>
      </Modal>
    </View>
  );
}

function AgentBar({
  vacation,
  progress,
  onBack,
}: {
  vacation: Vacation | null;
  progress: string;
  onBack: () => void;
}) {
  if (!vacation) return null;
  return (
    <View style={styles.agentBar}>
      <Pressable onPress={onBack} style={styles.backBtn} hitSlop={10}>
        <Ionicons name="arrow-back" size={20} color={colors.ink} />
      </Pressable>
      <Avatar
        prenom={vacation.agent?.prenom}
        nom={vacation.agent?.nom}
        size={40}
      />
      <View style={{ flex: 1 }}>
        <Text style={styles.agentBarName} numberOfLines={1}>
          {vacation.agent?.prenom} {vacation.agent?.nom}
        </Text>
        <Text style={styles.agentBarMeta} numberOfLines={1}>
          {progress} · {vacation.poste?.nom ?? vacation.site?.nom}
        </Text>
      </View>
    </View>
  );
}

function Banner({ tone, text }: { tone: "error" | "ok"; text: string }) {
  const err = tone === "error";
  return (
    <View style={[styles.banner, err ? styles.bannerError : styles.bannerOk]}>
      <Ionicons
        name={err ? "alert-circle" : "checkmark-circle"}
        size={16}
        color={err ? colors.danger : colors.teal}
      />
      <Text style={[styles.bannerText, err && { color: colors.danger }]}>
        {text}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.paper },
  flex: { flex: 1 },
  listContent: {
    padding: spacing.lg,
    paddingBottom: spacing.xxl,
    gap: spacing.md,
  },
  listHeader: { gap: spacing.sm, marginBottom: spacing.sm },
  listTitle: { ...typography.title, color: colors.ink },
  listLead: { ...typography.body, color: colors.inkMuted },
  countPill: {
    alignSelf: "flex-start",
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: colors.tealSoft,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs + 2,
    borderRadius: radii.full,
    marginTop: spacing.xs,
  },
  countText: { ...typography.caption, color: colors.tealDark },
  list: { gap: spacing.sm },
  agentCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    ...shadows.soft,
  },
  agentCardLocked: {
    borderColor: colors.border,
    backgroundColor: colors.paperMuted,
    opacity: 0.85,
  },
  chevronLocked: {
    backgroundColor: colors.paperMuted,
  },
  agentCopy: { flex: 1, gap: 2 },
  agentName: { ...typography.bodyStrong, color: colors.ink },
  agentMeta: { ...typography.caption, color: colors.inkMuted },
  statusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    marginTop: 4,
  },
  chevron: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: colors.tealSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  refreshRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: spacing.sm,
    paddingVertical: spacing.lg,
  },
  refreshText: { ...typography.caption, color: colors.inkMuted },
  agentBar: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  backBtn: {
    width: 36,
    height: 36,
    borderRadius: 12,
    backgroundColor: colors.paperMuted,
    alignItems: "center",
    justifyContent: "center",
  },
  agentBarName: { ...typography.bodyStrong, color: colors.ink },
  agentBarMeta: { ...typography.caption, color: colors.inkMuted, marginTop: 2 },
  stepContent: {
    padding: spacing.lg,
    paddingBottom: spacing.xxl,
    gap: spacing.md,
  },
  stepTitle: { ...typography.title, color: colors.ink },
  stepLead: { ...typography.body, color: colors.inkMuted, marginBottom: spacing.sm },
  fieldLabel: {
    ...typography.caption,
    color: colors.inkFaint,
    textTransform: "uppercase",
    letterSpacing: 0.7,
  },
  photoBox: {
    borderRadius: radii.lg,
    overflow: "hidden",
    backgroundColor: colors.white,
    borderWidth: 1.5,
    borderColor: colors.border,
    ...shadows.soft,
  },
  photo: { width: "100%", height: 280 },
  photoEmpty: {
    height: 280,
    alignItems: "center",
    justifyContent: "center",
    gap: spacing.sm,
    backgroundColor: colors.paperMuted,
  },
  photoIcon: {
    width: 56,
    height: 56,
    borderRadius: 18,
    backgroundColor: colors.white,
    alignItems: "center",
    justifyContent: "center",
  },
  photoTitle: { ...typography.subtitle, color: colors.ink },
  photoHint: { ...typography.caption, color: colors.inkMuted },
  photoBadge: {
    position: "absolute",
    right: spacing.md,
    bottom: spacing.md,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: colors.overlay,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radii.full,
  },
  photoBadgeText: { ...typography.caption, color: colors.white },
  thumbRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.md,
  },
  thumb: {
    width: 64,
    height: 64,
    borderRadius: radii.sm,
    backgroundColor: colors.paperMuted,
  },
  link: { ...typography.bodyStrong, color: colors.teal },
  noteToggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: spacing.sm,
    paddingVertical: spacing.sm,
  },
  noteToggleText: { ...typography.caption, color: colors.inkMuted },
  banner: {
    flexDirection: "row",
    gap: spacing.sm,
    alignItems: "center",
    borderRadius: radii.md,
    padding: spacing.md,
  },
  bannerError: { backgroundColor: colors.dangerSoft },
  bannerOk: { backgroundColor: colors.tealSoft },
  bannerText: { ...typography.caption, color: colors.tealDark, flex: 1 },
  doneWrap: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: spacing.xl,
    gap: spacing.sm,
  },
  doneIcon: {
    width: 72,
    height: 72,
    borderRadius: 24,
    backgroundColor: colors.teal,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: spacing.md,
  },
  doneTitle: { ...typography.title, color: colors.ink, textAlign: "center" },
  doneLead: {
    ...typography.body,
    color: colors.inkMuted,
    textAlign: "center",
  },
  doneHint: {
    ...typography.caption,
    color: colors.inkFaint,
    textAlign: "center",
    marginTop: spacing.sm,
  },
  cameraWrap: { flex: 1, backgroundColor: colors.ink },
  camera: { flex: 1 },
  cameraFrame: {
    position: "absolute",
    top: "22%",
    alignSelf: "center",
    width: "78%",
    height: "42%",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.55)",
    borderRadius: radii.xl,
  },
  cameraActions: {
    padding: spacing.xl,
    gap: spacing.md,
    backgroundColor: colors.paper,
  },
  cameraHint: {
    ...typography.bodyStrong,
    color: colors.ink,
    textAlign: "center",
  },
});
