"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import {
  isEmailIdentifiant,
  unifiedLoginSchema,
  type UnifiedLoginFormValues,
} from "@/domain/schemas/auth";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Input } from "@/presentation/components/ui/Input";
import { PasswordInput } from "@/presentation/components/ui/PasswordInput";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { homePathForUser } from "@/shared/lib/can";

export default function LoginPage() {
  const { login, loginTerrain, token, user, isLoading } = useAuth();
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);

  const form = useForm<UnifiedLoginFormValues>({
    resolver: zodResolver(unifiedLoginSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: { identifiant: "", secret: "" },
  });

  const identifiantWatch = form.watch("identifiant");
  const asEmail = isEmailIdentifiant(identifiantWatch ?? "");

  useEffect(() => {
    if (!isLoading && token && user) {
      router.replace(homePathForUser(user));
    }
  }, [isLoading, token, user, router]);

  if (isLoading || token) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#EEF0EA]">
        <Spinner className="size-8 text-teal" />
      </div>
    );
  }

  function parseLoginError(err: unknown): string {
    if (axios.isAxiosError(err)) {
      if (!err.response) {
        return "API injoignable. Vérifiez que le backend tourne (php artisan serve).";
      }
      const data = err.response.data as
        | {
            message?: string;
            errors?: Record<string, string[]>;
          }
        | undefined;
      const fieldError =
        data?.errors?.email?.[0] ??
        data?.errors?.password?.[0] ??
        data?.errors?.matricule?.[0] ??
        data?.errors?.pin?.[0];
      return fieldError ?? data?.message ?? "Identifiants incorrects.";
    }
    return "Connexion impossible. Réessayez.";
  }

  return (
    <div className="relative flex h-dvh flex-col overflow-hidden lg:grid lg:grid-cols-2">
      <aside className="relative isolate flex shrink-0 items-center gap-3 overflow-hidden bg-[#1E2714] px-5 py-4 text-white lg:flex-col lg:items-stretch lg:justify-between lg:px-12 lg:py-14">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_20%_0%,rgba(74,92,56,0.55),transparent_55%),radial-gradient(ellipse_at_90%_80%,rgba(49,31,56,0.55),transparent_50%),linear-gradient(165deg,#1E2714_0%,#2F3A24_48%,#1A0A24_100%)]"
        />
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.07]"
          style={{
            backgroundImage:
              "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")",
          }}
        />

        <div className="relative z-10 flex items-center gap-3 animate-fade-in lg:block">
          <div className="inline-flex shrink-0 items-center gap-3 rounded-xl bg-white/95 p-1.5 shadow-[0_12px_40px_rgba(0,0,0,0.28)] ring-1 ring-white/40 lg:rounded-2xl lg:p-3">
            <Image
              src="/logo.png"
              alt="Logo S.I.S — Société Ivoirienne de Sécurité"
              width={88}
              height={88}
              priority
              className="size-9 object-contain lg:h-[4.5rem] lg:w-[4.5rem] lg:sm:h-[5.5rem] lg:sm:w-[5.5rem]"
            />
          </div>
          <div className="lg:mt-6">
            <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/55 lg:text-[11px] lg:tracking-[0.28em]">
              Société Ivoirienne de Sécurité
            </p>
            <h1 className="font-sans text-xl font-extrabold tracking-tight text-white lg:mt-3 lg:max-w-md lg:text-4xl lg:sm:text-5xl lg:text-[3.25rem] lg:leading-[1.05]">
              S.I.S
            </h1>
          </div>
        </div>

        <p className="relative z-10 mt-3 hidden max-w-sm text-base leading-relaxed text-white/78 animate-fade-in lg:block lg:text-lg">
          Gardiennage &amp; opérations — Côte d’Ivoire.
        </p>

        <div className="relative z-10 mt-10 hidden animate-fade-in lg:block">
          <p className="max-w-sm text-sm leading-relaxed text-white/60">
            Un seul accès pour le personnel autorisé — bureau ou terrain.
          </p>
        </div>
      </aside>

      <main className="relative min-h-0 flex-1 overflow-y-auto bg-[#F4F5F2] px-5 py-6 sm:px-8 lg:flex lg:items-center lg:justify-center lg:px-12 lg:py-10">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(20,26,16,0.05)_1px,transparent_0)] [background-size:18px_18px]"
        />

        <div className="relative z-10 mx-auto w-full max-w-[26rem] animate-fade-in">
          <div className="rounded-[1.35rem] border border-border/80 bg-white/95 p-6 shadow-[0_20px_50px_-28px_rgba(30,39,20,0.45)] backdrop-blur-sm sm:p-8">
            <header className="mb-6">
              <h2 className="text-2xl font-bold tracking-tight text-ink">
                Connexion
              </h2>
              <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">
                Saisissez votre email ou votre matricule.
              </p>
            </header>

            <form
              className="space-y-4"
              noValidate
              onSubmit={form.handleSubmit(async (values) => {
                setError(null);
                const id = values.identifiant.trim();
                const secret = values.secret.trim();
                try {
                  const nextUser = isEmailIdentifiant(id)
                    ? await login(id, secret)
                    : await loginTerrain(id.toUpperCase(), secret);
                  router.replace(homePathForUser(nextUser));
                } catch (err) {
                  setError(parseLoginError(err));
                }
              })}
            >
              <RequiredFieldsLegend />

              <Input
                label="Identifiant"
                autoComplete="username"
                autoFocus
                requiredMark
                placeholder="email@sis.ci ou RD-2001"
                hint={
                  asEmail
                    ? "Connexion back-office détectée"
                    : identifiantWatch.trim()
                      ? "Connexion terrain détectée"
                      : "Email professionnel ou matricule contrôleur"
                }
                error={form.formState.errors.identifiant?.message}
                {...form.register("identifiant")}
              />

              <PasswordInput
                label={asEmail ? "Mot de passe" : "Mot de passe / PIN"}
                autoComplete="current-password"
                inputMode={asEmail ? "text" : "numeric"}
                requiredMark
                placeholder={asEmail ? "••••••••" : "••••"}
                error={form.formState.errors.secret?.message}
                {...form.register("secret")}
              />

              {error ? <Alert tone="danger">{error}</Alert> : null}

              <Button
                type="submit"
                className="mt-2 w-full"
                loading={form.formState.isSubmitting}
              >
                {form.formState.isSubmitting ? "Connexion…" : "Se connecter"}
              </Button>
            </form>
          </div>

          <p className="mt-6 text-center text-xs text-ink-faint">
            © {new Date().getFullYear()} S.I.S — Accès réservé au personnel
            autorisé.
          </p>
        </div>
      </main>
    </div>
  );
}
