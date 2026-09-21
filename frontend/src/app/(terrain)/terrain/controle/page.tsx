"use client";

import { Suspense } from "react";
import { Spinner } from "@/presentation/components/ui/Spinner";
import ControleClient from "./ControleClient";

export default function TerrainControlePage() {
  return (
    <Suspense
      fallback={
        <div className="flex justify-center py-16">
          <Spinner className="size-7" />
        </div>
      }
    >
      <ControleClient />
    </Suspense>
  );
}
