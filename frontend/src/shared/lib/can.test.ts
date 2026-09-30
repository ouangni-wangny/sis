import { describe, expect, it } from "vitest";
import {
  can,
  canAny,
  canPayerPaie,
  canSeeSalaire,
  canViewPaie,
} from "@/shared/lib/can";
import type { User } from "@/domain/types/entities";
import { navigation, flatNavigationItems } from "@/shared/config/navigation";

function user(
  partial: Partial<User> & Pick<User, "permissions" | "roles">,
): User {
  return {
    id: "u1",
    nom: "Test",
    prenom: "User",
    email: "test@sis.ci",
    matricule: null,
    type: "web",
    statut: "actif",
    ...partial,
  };
}

describe("can / permissions métier", () => {
  it("refuse la trésorerie au RH et au commercial", () => {
    const rh = user({
      roles: ["rh"],
      permissions: ["paie.view", "paie.manage", "contrats.manage"],
    });
    const commercial = user({
      roles: ["commercial"],
      permissions: ["factures.view", "paiements.manage"],
    });

    expect(can(rh, "tresorerie.view")).toBe(false);
    expect(can(commercial, "tresorerie.view")).toBe(false);
    expect(canAny(rh, ["tresorerie.view", "tresorerie.manage"])).toBe(false);
  });

  it("autorise la trésorerie au comptable", () => {
    const comptable = user({
      roles: ["comptable"],
      permissions: [
        "tresorerie.view",
        "tresorerie.manage",
        "factures.view",
        "paie.view",
        "paie.payer",
      ],
    });

    expect(can(comptable, "tresorerie.view")).toBe(true);
    expect(canViewPaie(comptable)).toBe(true);
    expect(canPayerPaie(comptable)).toBe(true);
    expect(canSeeSalaire(comptable)).toBe(false);
  });

  it("refuse la paie au commercial et l’autorise au comptable", () => {
    const commercial = user({
      roles: ["commercial"],
      permissions: ["factures.view", "paiements.manage"],
    });
    const comptable = user({
      roles: ["comptable"],
      permissions: ["paie.view", "paie.payer", "factures.view"],
    });

    expect(canViewPaie(commercial)).toBe(false);
    expect(canViewPaie(comptable)).toBe(true);
    expect(canSeeSalaire(comptable)).toBe(false);
  });
});

describe("navigation trésorerie / paie", () => {
  it("réserve les entrées trésorerie à tresorerie.view|manage", () => {
    const treso = flatNavigationItems().filter((i) =>
      i.href.startsWith("/tresorerie"),
    );

    expect(treso.length).toBeGreaterThan(0);
    for (const item of treso) {
      expect(item.permissions.some((p) => p.startsWith("tresorerie."))).toBe(
        true,
      );
    }
  });

  it("expose la paie avec paie.view pour RH/comptable", () => {
    const paie = navigation
      .flatMap((g) => g.items)
      .find((i) => i.href === "/rh/paie");

    expect(paie?.permissions).toContain("paie.view");
    expect(paie?.permissions).toContain("paie.payer");
  });
});
