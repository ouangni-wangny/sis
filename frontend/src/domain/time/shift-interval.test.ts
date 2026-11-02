import { describe, expect, it } from "vitest";
import { isOvernight, minutesOf } from "@/domain/time/shift-interval";

describe("shift-interval", () => {
  it("lit les heures avec ou sans secondes", () => {
    expect(minutesOf("18:30")).toBe(1110);
    expect(minutesOf("18:30:00")).toBe(1110);
    expect(minutesOf("n'importe quoi")).toBeNull();
  });

  it("reconnaît une nuit renvoyée par l'API en HH:MM:SS", () => {
    expect(isOvernight("18:30:00", "06:30:00")).toBe(true);
    expect(isOvernight("06:30:00", "18:30:00")).toBe(false);
  });
});
