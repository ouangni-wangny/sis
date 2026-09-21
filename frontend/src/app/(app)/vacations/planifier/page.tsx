"use client";

import { Suspense } from "react";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { PlanifierAgentForm } from "./PlanifierAgentForm";

export default function PlanifierAgentPage() {
  return (
    <PermissionGate permission="vacations.create" title="Planifier un agent">
      <Suspense
        fallback={
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        }
      >
        <PlanifierAgentForm />
      </Suspense>
    </PermissionGate>
  );
}
