"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft, Camera, Check } from "lucide-react";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { controlesApi, vacationsApi } from "@/infrastructure/http/resources";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import {
  fileToJpegBase64,
  getCurrentCoords,
  uuid,
} from "@/shared/lib/terrain";
import {
  hintPassagesRequis,
  indexControlesParPassage,
  labelBadgeControle,
  statusControleVacation,
  type PassageStatus,
} from "@/domain/controle-passages";
import type { Controle, ControleResultat, Vacation } from "@/domain/types/entities";
import { formatDateTime } from "@/shared/lib/format";
import { cn } from "@/shared/lib/cn";

type Step = "list" | "photo" | "confirm" | "done";
type Presence = "present" | "absent" | null;
type ListTab = "en_poste" | "historique";

function initials(prenom?: string, nom?: string) {
  return (
    `${(prenom ?? "").charAt(0)}${(nom ?? "").charAt(0)}`.toUpperCase() || "?"
  );
}

function resultatLabel(resultat?: ControleResultat) {
  if (resultat === "present") return "Présent";
  if (resultat === "absent") return "Absent";
  return "Enregistré";
}

function Badge({
  tone,
  children,
}: {
  tone: "ok" | "danger" | "muted" | "partial";
  children: React.ReactNode;
}) {
  return (
    <span
      className={cn(
        "shrink-0 border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide",
        tone === "ok" && "border-[#0F6B5C]/30 bg-[#E6F2EF] text-[#0F6B5C]",
        tone === "danger" && "border-red-300 bg-red-50 text-danger",
        tone === "muted" && "border-border bg-paper-muted text-ink-muted",
        tone === "partial" && "border-amber-300 bg-amber-50 text-amber-800",
      )}
    >
      {children}
    </span>
  );
}

export default function ControleClient() {
  const { user } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const vacationId = searchParams.get("vacationId");
  const fileRef = useRef<HTMLInputElement>(null);
  const openedRef = useRef<string | null>(null);

  const [vacations, setVacations] = useState<Vacation[]>([]);
  const [history, setHistory] = useState<Controle[]>([]);
  const [listTab, setListTab] = useState<ListTab>("en_poste");
  const [statusByVacation, setStatusByVacation] = useState(
    () => new Map<string, PassageStatus>(),
  );
  const [step, setStep] = useState<Step>("list");
  const [selected, setSelected] = useState<Vacation | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [photoBase64, setPhotoBase64] = useState<string | null>(null);
  const [presence, setPresence] = useState<Presence>(null);
  const [confirmed, setConfirmed] = useState(false);
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [okMessage, setOkMessage] = useState<string | null>(null);
  const [doneResultat, setDoneResultat] = useState<ControleResultat | null>(
    null,
  );
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const noPerimetre = (user?.agent?.perimetres?.length ?? 0) === 0;

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [vacRes, todayCtrlRes, historyRes] = await Promise.all([
        vacationsApi.list({ en_poste: 1, per_page: 50 }),
        controlesApi.list({ aujourd_hui: 1, mes_controles: 1, per_page: 100 }),
        controlesApi.list({ mes_controles: 1, per_page: 30 }),
      ]);
      setVacations(vacRes.data);
      const byAgent = indexControlesParPassage(
        todayCtrlRes.data
          .filter((c) => c.controle_agent_id)
          .map((c) => ({
            controle_agent_id: c.controle_agent_id!,
            resultat: c.resultat,
            effectue_at: c.effectue_at,
          })),
      );
      const map = new Map<string, PassageStatus>();
      for (const v of vacRes.data) {
        map.set(v.id, statusControleVacation(v, byAgent));
      }
      setStatusByVacation(map);
      setHistory(historyRes.data);
    } catch (err) {
      setError(getApiErrorMessage(err, "Chargement impossible."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function resetForm() {
    setPhotoPreview(null);
    setPhotoBase64(null);
    setPresence(null);
    setConfirmed(false);
    setNote("");
  }

  function backToList() {
    resetForm();
    setSelected(null);
    setError(null);
    setStep("list");
    openedRef.current = null;
    router.replace("/terrain/controle");
  }

  function selectAgent(v: Vacation) {
    // Un agent déjà "Fait" reste sélectionnable pour un recontrôle — le
    // serveur applique le délai minimum entre deux contrôles et renvoie
    // une erreur explicite si c'est trop tôt (utile après une absence
    // constatée, pour vérifier que l'agent est revenu à son poste).
    setSelected(v);
    resetForm();
    setOkMessage(null);
    setError(null);
    setStep("photo");
  }

  useEffect(() => {
    if (!vacationId || loading || vacations.length === 0) return;
    if (openedRef.current === vacationId) return;
    const match = vacations.find((v) => v.id === vacationId);
    if (!match) return;
    openedRef.current = vacationId;
    selectAgent(match);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vacationId, vacations, loading]);

  async function onPickPhoto(file: File | null) {
    if (!file) return;
    try {
      const prepared = await fileToJpegBase64(file);
      setPhotoPreview(prepared.previewUrl);
      setPhotoBase64(prepared.base64);
      setError(null);
    } catch (err) {
      setError(getApiErrorMessage(err, "Photo invalide."));
    }
  }

  async function submit() {
    if (!user?.agent?.id || !selected || !photoBase64 || !presence) {
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
      const created = await controlesApi.createPresence({
        agent_id: user.agent.id,
        controle_agent_id: selected.agent_id,
        site_id: selected.site_id,
        poste_id: selected.poste_id,
        latitude: coords.latitude,
        longitude: coords.longitude,
        resultat: presence,
        commentaire: note.trim() || undefined,
        client_uuid: uuid(),
        photo_base64: photoBase64,
      });
      const resultat = created.data.resultat;
      setDoneResultat(resultat);
      setOkMessage(
        resultat === "absent"
          ? `Absence enregistrée pour ${selected.agent?.prenom} ${selected.agent?.nom}`
          : `Présence enregistrée pour ${selected.agent?.prenom} ${selected.agent?.nom}`,
      );
      setStep("done");
      void load();
    } catch (err) {
      setError(getApiErrorMessage(err, "Échec de l’enregistrement."));
    } finally {
      setBusy(false);
    }
  }

  const pendingCount = vacations.filter((v) => {
    const s = statusByVacation.get(v.id);
    return !s?.complet;
  }).length;

  return (
    <div className="px-4 pt-5">
      {step !== "list" && step !== "done" && selected ? (
        <div className="mb-4 border border-border bg-white">
          <div className="flex items-center gap-3 border-b border-border px-3 py-2.5">
            <button
              type="button"
              onClick={() => {
                if (step === "confirm") {
                  setStep("photo");
                  setError(null);
                } else {
                  backToList();
                }
              }}
              className="border border-border p-1.5 text-ink-muted hover:bg-paper-muted"
              aria-label="Retour"
            >
              <ArrowLeft className="size-4" />
            </button>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold">
                {selected.agent?.prenom} {selected.agent?.nom}
              </p>
              <p className="truncate text-xs text-ink-muted">
                {selected.site?.nom} · {selected.poste?.nom ?? "Poste"}
              </p>
              {(() => {
                const st = statusByVacation.get(selected.id);
                const hint = st ? hintPassagesRequis(st) : null;
                if (!hint && !st?.manquants.length) return null;
                const missing =
                  st && !st.complet
                    ? st.manquants
                        .map((p) => (p === "matin" ? "matin" : "soir"))
                        .join(" + ")
                    : null;
                return (
                  <p className="mt-0.5 truncate text-[10px] text-[#0F6B5C]">
                    {hint}
                    {missing ? ` · reste : ${missing}` : null}
                  </p>
                );
              })()}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-px bg-border">
            <div
              className={cn(
                "bg-white px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide",
                step === "photo"
                  ? "text-[#0F6B5C]"
                  : "text-ink-faint",
              )}
            >
              1 · Photo
            </div>
            <div
              className={cn(
                "bg-white px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide",
                step === "confirm"
                  ? "text-[#0F6B5C]"
                  : "text-ink-faint",
              )}
            >
              2 · Validation
            </div>
          </div>
        </div>
      ) : (
        <header className="mb-4 border-b border-border pb-3">
          <div className="flex items-end justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-ink">Contrôles</h1>
              <p className="mt-0.5 text-sm text-ink-muted">
                {pendingCount > 0
                  ? `${pendingCount} agent${pendingCount > 1 ? "s" : ""} à contrôler`
                  : "Mission du jour"}
              </p>
            </div>
            <button
              type="button"
              className="text-xs font-semibold text-[#0F6B5C]"
              onClick={() => void load()}
            >
              Actualiser
            </button>
          </div>
        </header>
      )}

      {error ? (
        <div className="mb-3 border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-danger">
          {error}
        </div>
      ) : null}

      {step === "list" ? (
        loading ? (
          <div className="flex justify-center py-16">
            <Spinner className="size-7" />
          </div>
        ) : (
          <div className="space-y-3">
            <div
              className="grid h-10 grid-cols-2 border border-border bg-white"
              role="tablist"
              aria-label="Contrôles"
            >
              {(
                [
                  { id: "en_poste" as const, label: "En poste" },
                  { id: "historique" as const, label: "Historique" },
                ] as const
              ).map((tab) => {
                const active = listTab === tab.id;
                return (
                  <button
                    key={tab.id}
                    type="button"
                    role="tab"
                    aria-selected={active}
                    onClick={() => setListTab(tab.id)}
                    className={cn(
                      "text-xs font-semibold transition",
                      active
                        ? "bg-[#0F6B5C] text-white"
                        : "bg-white text-ink-muted hover:text-ink",
                    )}
                  >
                    {tab.label}
                  </button>
                );
              })}
            </div>

            {listTab === "en_poste" ? (
              vacations.length === 0 ? (
                <div className="border border-dashed border-border bg-white px-4 py-12 text-center text-sm text-ink-muted">
                  {noPerimetre
                    ? "Aucune zone ne vous est assignée. Contactez votre administrateur."
                    : "Aucun agent en poste aujourd’hui."}
                </div>
              ) : (
                <ul className="divide-y divide-border border border-border bg-white">
                  {vacations.map((v) => {
                    const status = statusByVacation.get(v.id);
                    const badge = status
                      ? labelBadgeControle(status)
                      : { text: "À faire", tone: "muted" as const };
                    const hint = status ? hintPassagesRequis(status) : null;
                    return (
                      <li key={v.id}>
                        <button
                          type="button"
                          onClick={() => selectAgent(v)}
                          className="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-paper-muted/60"
                        >
                          <div className="flex size-10 items-center justify-center border border-[#0F6B5C]/25 bg-[#E6F2EF] text-xs font-bold text-[#0F6B5C]">
                            {initials(v.agent?.prenom, v.agent?.nom)}
                          </div>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold">
                              {v.agent?.prenom} {v.agent?.nom}
                            </p>
                            <p className="truncate text-xs text-ink-muted">
                              {v.site?.nom} · {v.poste?.nom ?? "Poste"}
                            </p>
                            {hint ? (
                              <p className="mt-0.5 truncate text-[10px] text-ink-faint">
                                {hint}
                              </p>
                            ) : null}
                          </div>
                          <Badge tone={badge.tone}>{badge.text}</Badge>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )
            ) : history.length === 0 ? (
              <div className="border border-dashed border-border bg-white px-4 py-12 text-center text-sm text-ink-muted">
                Aucun contrôle enregistré pour le moment.
              </div>
            ) : (
              <ul className="divide-y divide-border border border-border bg-white">
                {history.map((c) => (
                  <li key={c.id} className="px-3 py-3">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-ink">
                          {c.controle_agent
                            ? `${c.controle_agent.prenom} ${c.controle_agent.nom}`
                            : "Agent"}
                        </p>
                        <p className="truncate text-xs text-ink-muted">
                          {c.site?.nom ?? "Site"} · {c.poste?.nom ?? "Poste"}
                        </p>
                        <p className="mt-1 text-[11px] text-ink-faint">
                          {formatDateTime(c.effectue_at)}
                        </p>
                      </div>
                      <Badge
                        tone={c.resultat === "absent" ? "danger" : "ok"}
                      >
                        {resultatLabel(c.resultat)}
                      </Badge>
                    </div>
                    {c.commentaire ? (
                      <p className="mt-2 line-clamp-2 border-l-2 border-border pl-2 text-xs text-ink-muted">
                        {c.commentaire}
                      </p>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )
      ) : null}

      {step === "photo" ? (
        <div className="space-y-3">
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            capture="environment"
            className="hidden"
            onChange={(e) => void onPickPhoto(e.target.files?.[0] ?? null)}
          />
          {photoPreview ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={photoPreview}
              alt="Photo contrôle"
              className="h-64 w-full border border-border object-cover"
            />
          ) : (
            <button
              type="button"
              onClick={() => fileRef.current?.click()}
              className="flex h-64 w-full flex-col items-center justify-center gap-2 border border-dashed border-border bg-white text-ink-muted"
            >
              <Camera className="size-8 text-[#0F6B5C]" />
              <span className="text-sm font-semibold">
                Prendre / choisir une photo
              </span>
            </button>
          )}
          <div className="flex gap-2">
            {photoPreview ? (
              <Button
                type="button"
                variant="secondary"
                className="flex-1 !rounded-none"
                onClick={() => fileRef.current?.click()}
              >
                Reprendre
              </Button>
            ) : null}
            <Button
              type="button"
              className="flex-1 !rounded-none"
              disabled={!photoBase64}
              onClick={() => setStep("confirm")}
            >
              Continuer
            </Button>
          </div>
        </div>
      ) : null}

      {step === "confirm" ? (
        <div className="space-y-4">
          <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
              Présence
            </p>
            <div className="grid grid-cols-2 gap-2">
              {(
                [
                  ["present", "Présent"],
                  ["absent", "Absent"],
                ] as const
              ).map(([value, label]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setPresence(value)}
                  className={cn(
                    "border px-3 py-3.5 text-sm font-semibold transition",
                    presence === value
                      ? value === "present"
                        ? "border-[#0F6B5C] bg-[#E6F2EF] text-[#0F6B5C]"
                        : "border-danger bg-red-50 text-danger"
                      : "border-border bg-white text-ink-muted",
                  )}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          <label className="flex items-start gap-2.5 border border-border bg-white px-3 py-3 text-sm">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              className="mt-0.5 size-4 rounded-none border-border"
            />
            <span>
              Je confirme avoir contrôlé cet agent sur le site indiqué.
            </span>
          </label>

          <div>
            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-faint">
              Note (optionnel)
            </label>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={3}
              className="w-full border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-[#0F6B5C]"
              placeholder="Observation…"
            />
          </div>

          <Button
            type="button"
            className="w-full !rounded-none"
            loading={busy}
            disabled={!presence || !confirmed}
            onClick={() => void submit()}
          >
            Valider le contrôle
          </Button>
        </div>
      ) : null}

      {step === "done" ? (
        <div className="border border-border bg-white px-5 py-10 text-center">
          <div
            className={cn(
              "mx-auto mb-3 flex size-14 items-center justify-center border",
              doneResultat === "absent"
                ? "border-red-300 bg-red-50 text-danger"
                : "border-[#0F6B5C]/30 bg-[#E6F2EF] text-[#0F6B5C]",
            )}
          >
            <Check className="size-7" />
          </div>
          <p className="text-lg font-bold text-ink">Contrôle enregistré</p>
          <p className="mt-2 text-sm text-ink-muted">{okMessage}</p>
          <div className="mt-6 flex flex-col gap-2">
            <Button type="button" className="!rounded-none" onClick={backToList}>
              Contrôler un autre agent
            </Button>
            <Button
              type="button"
              variant="secondary"
              className="!rounded-none"
              onClick={() => router.push("/terrain")}
            >
              Retour à l’accueil
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
