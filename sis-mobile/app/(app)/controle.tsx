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
import type { PresenceValue } from "@/components/PresenceToggle";
import { ControlesPassagePill, StatusPill } from "@/components/StatusPill";
import { StepFooter } from "@/components/StepFooter";
import {
  disponibiliteControle,
  indexControlesParPassage,
  labelHorairesVacation,
  statusControleVacation,
  type PassageStatus,
} from "@/lib/controlePassages";
import { getCurrentCoords } from "@/lib/location";
import { prepareControlPhoto } from "@/lib/prepareControlPhoto";
import { colors, radii, shadows, spacing, typography } from "@/theme/tokens";
import type { ControleResultat, Vacation } from "@/types/api";

type Step = "list" | "presence" | "photo" | "confirm" | "done";

function uuid() {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

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
    if (status) {
      const dispo = disponibiliteControle(v, status);
      if (!dispo.cliquable) {
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
        setError(dispo.motif);
        return;
      }
    }
    void Haptics.selectionAsync();
    setSelected(v);
    resetForm();
    setOkMessage(null);
    setError(null);
    setStep("presence");
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
    if (!token || !user?.agent?.id || !selected || !presence) {
      setError("Complétez toutes les étapes.");
      return;
    }
    if (presence === "present" && !photoBase64) {
      setError("Une photo est obligatoire pour une présence.");
      setStep("photo");
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
        resultat: presence,
        commentaire: note.trim() ? `${auto}. ${note.trim()}` : auto,
        client_uuid: uuid(),
        ...(photoBase64 ? { photo_base64: photoBase64 } : {}),
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

  const progressLabel =
    step === "list"
      ? "Choisir un agent"
      : step === "presence"
        ? "Étape 1 · Présence"
        : step === "photo"
          ? "Étape 2 · Photo"
          : step === "confirm"
            ? presence === "absent"
              ? "Étape 2 · Validation"
              : "Étape 3 · Validation"
            : "Terminé";

  return (
    <View style={styles.root}>
      {step !== "list" && step !== "done" ? (
        <AgentBar
          vacation={selected}
          progress={progressLabel}
          onBack={() => {
            setError(null);
            if (step === "confirm") {
              setStep(presence === "present" ? "photo" : "presence");
            } else if (step === "photo") {
              setStep("presence");
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
              Contrôle possible uniquement dans le créneau affiché (matin avant
              14h, soir après).
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
              {[...vacations]
                .map((v) => {
                  const status = statusByVacation.get(v.id);
                  const dispo = status
                    ? disponibiliteControle(v, status)
                    : null;
                  return { v, status, dispo };
                })
                .sort((a, b) => {
                  // Cliquables d’abord, puis hors créneau, puis complets.
                  const rank = (x: typeof a) => {
                    if (x.status?.complet) return 2;
                    if (x.dispo?.cliquable) return 0;
                    return 1;
                  };
                  return rank(a) - rank(b);
                })
                .map(({ v, status, dispo }) => {
                  const locked = Boolean(dispo && !dispo.cliquable);
                  const actif = Boolean(dispo?.cliquable && !status?.complet);
                  const fenetreActive = dispo?.fenetresManquantes.find(
                    (f) => f.passage === dispo.passageActif,
                  );
                  const fenetreLabel = fenetreActive
                    ? `${fenetreActive.debutHm}–${fenetreActive.finHm}`
                    : dispo?.fenetresManquantes
                        .map((f) => `${f.debutHm}–${f.finHm}`)
                        .join(" · ") || null;
                  return (
                    <Pressable
                      key={v.id}
                      onPress={() => selectAgent(v)}
                      disabled={locked}
                      style={({ pressed }) => [
                        styles.agentCard,
                        actif && styles.agentCardActive,
                        locked && styles.agentCardLocked,
                        status?.complet && styles.agentCardDone,
                        pressed &&
                          !locked && {
                            opacity: 0.92,
                            transform: [{ scale: 0.99 }],
                          },
                      ]}
                    >
                      <Avatar prenom={v.agent?.prenom} nom={v.agent?.nom} />
                      <View style={styles.agentCopy}>
                        <Text
                          style={[
                            styles.agentName,
                            locked && styles.agentNameLocked,
                          ]}
                        >
                          {v.agent?.prenom} {v.agent?.nom}
                        </Text>
                        <Text style={styles.agentMeta}>
                          {v.site?.nom}
                          {v.poste ? ` · ${v.poste.nom}` : ""}
                        </Text>
                        <Text style={styles.agentShift}>
                          Vacation {labelHorairesVacation(v)}
                        </Text>
                        {fenetreLabel ? (
                          <View style={styles.fenetreRow}>
                            <Ionicons
                              name="time-outline"
                              size={13}
                              color={
                                locked ? colors.inkFaint : colors.tealDark
                              }
                            />
                            <Text
                              style={[
                                styles.fenetreText,
                                locked && styles.fenetreTextLocked,
                              ]}
                            >
                              Contrôle {fenetreLabel}
                            </Text>
                          </View>
                        ) : null}
                        {locked ? (
                          <Text style={styles.lockedHint}>
                            Hors créneau — ligne désactivée
                          </Text>
                        ) : null}
                        <View style={styles.statusRow}>
                          {status ? (
                            <ControlesPassagePill status={status} compact />
                          ) : null}
                          {status?.dernierEffectueAt ? (
                            <Text style={styles.agentMeta}>
                              {new Date(
                                status.dernierEffectueAt,
                              ).toLocaleTimeString("fr-FR", {
                                hour: "2-digit",
                                minute: "2-digit",
                              })}
                            </Text>
                          ) : null}
                        </View>
                      </View>
                      <View
                        style={[
                          styles.chevron,
                          locked && styles.chevronLocked,
                          actif && styles.chevronActive,
                        ]}
                      >
                        <Ionicons
                          name={locked ? "lock-closed" : "chevron-forward"}
                          size={16}
                          color={locked ? colors.inkFaint : colors.teal}
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

      {step === "presence" && selected ? (
        <>
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.stepContent}
            keyboardShouldPersistTaps="handled"
          >
            <Text style={styles.stepTitle}>L’agent est-il au poste ?</Text>
            <Text style={styles.stepLead}>
              Choisissez d’abord le constat. La photo n’est demandée qu’en cas
              de présence.
            </Text>

            <Pressable
              onPress={() => {
                setPresence("present");
                setConfirmed(false);
                setError(null);
                void Haptics.selectionAsync();
                setStep("photo");
              }}
              style={({ pressed }) => [
                styles.presenceChoice,
                styles.presenceChoiceOk,
                pressed && { opacity: 0.9 },
              ]}
            >
              <Text style={styles.presenceChoiceTitleOk}>Présent</Text>
              <Text style={styles.presenceChoiceHintOk}>
                Puis photographier l’agent au poste
              </Text>
            </Pressable>

            <Pressable
              onPress={() => {
                setPresence("absent");
                setPhotoUri(null);
                setPhotoBase64(null);
                setConfirmed(false);
                setError(null);
                void Haptics.selectionAsync();
                setStep("confirm");
              }}
              style={({ pressed }) => [
                styles.presenceChoice,
                styles.presenceChoiceDanger,
                pressed && { opacity: 0.9 },
              ]}
            >
              <Text style={styles.presenceChoiceTitleDanger}>Absent</Text>
              <Text style={styles.presenceChoiceHintDanger}>
                Pas de photo — validation directe de l’absence
              </Text>
            </Pressable>

            {error ? <Banner tone="error" text={error} /> : null}
          </ScrollView>
          <StepFooter
            secondaryLabel="Changer d’agent"
            onSecondary={backToList}
            primaryLabel="Choisir Présent ou Absent"
            onPrimary={() => undefined}
            primaryDisabled
          />
        </>
      ) : null}

      {step === "photo" && selected ? (
        <>
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.stepContent}
            keyboardShouldPersistTaps="handled"
          >
            <Text style={styles.stepTitle}>Photo de présence</Text>
            <Text style={styles.stepLead}>
              Preuve visuelle obligatoire pour confirmer que l’agent est au
              poste.
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
            secondaryLabel="Retour"
            onSecondary={() => {
              setStep("presence");
              setError(null);
            }}
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
            <Text style={styles.stepTitle}>
              {presence === "absent"
                ? "Confirmer l’absence"
                : "Confirmer la présence"}
            </Text>
            <Text style={styles.stepLead}>
              GPS enregistré automatiquement à la validation.
            </Text>

            <View
              style={[
                styles.constatBox,
                presence === "absent" && styles.constatBoxDanger,
              ]}
            >
              <Text style={styles.fieldLabel}>Constat</Text>
              <Text
                style={[
                  styles.constatText,
                  presence === "absent" && styles.constatTextDanger,
                ]}
              >
                {presence === "absent"
                  ? "Agent absent du poste"
                  : "Agent présent"}
              </Text>
              <Pressable
                onPress={() => {
                  setConfirmed(false);
                  setStep("presence");
                }}
                hitSlop={8}
              >
                <Text style={styles.link}>Modifier</Text>
              </Pressable>
            </View>

            {presence === "present" && photoUri ? (
              <View style={styles.thumbRow}>
                <Image source={{ uri: photoUri }} style={styles.thumb} />
                <Pressable onPress={() => setStep("photo")} hitSlop={8}>
                  <Text style={styles.link}>Modifier la photo</Text>
                </Pressable>
              </View>
            ) : null}

            {presence === "absent" ? (
              <Text style={styles.agentMeta}>
                Aucune photo requise : l’agent n’est pas au poste.
              </Text>
            ) : null}

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
            secondaryLabel={
              presence === "present" ? "Retour photo" : "Retour"
            }
            onSecondary={() => {
              setStep(presence === "present" ? "photo" : "presence");
              setError(null);
            }}
            primaryLabel="Valider le contrôle"
            onPrimary={submit}
            primaryLoading={busy}
            primaryDisabled={
              !presence ||
              !confirmed ||
              (presence === "present" && !photoBase64)
            }
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
          {doneResultat === "absent" &&
          user?.permissions?.includes("vacations.recouvrir") ? (
            <Button
              label="Désigner un remplaçant"
              variant="danger"
              onPress={() => {
                backToList();
                router.push("/(app)/recouvrir");
              }}
              style={{ marginTop: spacing.xl, alignSelf: "stretch" }}
            />
          ) : null}
          <Button
            label="Retour à la liste"
            variant={doneResultat === "absent" ? "secondary" : "primary"}
            onPress={backToList}
            style={{ marginTop: spacing.md, alignSelf: "stretch" }}
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
  agentCardActive: {
    borderColor: colors.teal,
    borderWidth: 1.5,
    backgroundColor: colors.white,
  },
  agentCardDone: {
    borderColor: colors.border,
    backgroundColor: colors.white,
    opacity: 0.92,
  },
  agentCardLocked: {
    borderColor: colors.border,
    backgroundColor: colors.paperMuted,
    opacity: 0.72,
    ...({ shadowOpacity: 0, elevation: 0 } as object),
  },
  agentCopy: { flex: 1, gap: 2 },
  agentName: { ...typography.bodyStrong, color: colors.ink },
  agentNameLocked: { color: colors.inkMuted },
  agentMeta: { ...typography.caption, color: colors.inkMuted },
  agentShift: {
    ...typography.caption,
    color: colors.inkFaint,
    marginTop: 1,
  },
  fenetreRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    marginTop: 3,
  },
  fenetreText: {
    ...typography.caption,
    color: colors.tealDark,
    fontWeight: "600",
  },
  fenetreTextLocked: {
    color: colors.inkFaint,
    fontWeight: "500",
  },
  lockedHint: {
    ...typography.caption,
    color: colors.inkFaint,
    marginTop: 2,
    fontStyle: "italic",
  },
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
  chevronActive: {
    backgroundColor: colors.tealSoft,
  },
  chevronLocked: {
    backgroundColor: "transparent",
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
  presenceChoice: {
    borderWidth: 1.5,
    borderRadius: radii.lg,
    padding: spacing.lg,
    gap: 4,
  },
  presenceChoiceOk: {
    borderColor: colors.teal,
    backgroundColor: colors.tealSoft,
  },
  presenceChoiceDanger: {
    borderColor: "#FECACA",
    backgroundColor: "#FEF2F2",
  },
  presenceChoiceTitleOk: {
    ...typography.bodyStrong,
    color: colors.tealDark,
  },
  presenceChoiceTitleDanger: {
    ...typography.bodyStrong,
    color: colors.danger,
  },
  presenceChoiceHintOk: {
    ...typography.caption,
    color: colors.tealDark,
    opacity: 0.85,
  },
  presenceChoiceHintDanger: {
    ...typography.caption,
    color: colors.danger,
    opacity: 0.85,
  },
  constatBox: {
    borderWidth: 1,
    borderColor: colors.teal,
    backgroundColor: colors.tealSoft,
    borderRadius: radii.lg,
    padding: spacing.md,
    gap: 4,
  },
  constatBoxDanger: {
    borderColor: "#FECACA",
    backgroundColor: "#FEF2F2",
  },
  constatText: {
    ...typography.bodyStrong,
    color: colors.tealDark,
  },
  constatTextDanger: {
    color: colors.danger,
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
