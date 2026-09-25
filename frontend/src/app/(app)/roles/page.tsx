"use client";

import { useState } from "react";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import {
  PermissionsTab,
  RolesTab,
} from "@/presentation/components/system/RolesPermissionsManager";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";

type RolesPageTab = "roles" | "permissions";

export default function RolesPage() {
  const [tab, setTab] = useState<RolesPageTab>("roles");

  return (
    <PermissionGate
      permission="system.roles.manage"
      title="Rôles & permissions"
    >
      <div className="space-y-6">
        <PageHeader
          title="Rôles & permissions"
          description="Créez des rôles métier, assignez des permissions et consultez le catalogue des droits disponibles."
        />

        <Tabs
          items={[
            { id: "roles", label: "Rôles" },
            { id: "permissions", label: "Permissions" },
          ]}
          value={tab}
          onChange={(id) => setTab(id as RolesPageTab)}
        />

        <TabPanel when="roles" active={tab}>
          <RolesTab />
        </TabPanel>
        <TabPanel when="permissions" active={tab}>
          <PermissionsTab />
        </TabPanel>
      </div>
    </PermissionGate>
  );
}
