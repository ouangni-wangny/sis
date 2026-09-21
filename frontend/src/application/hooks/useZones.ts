"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { zonesApi } from "@/infrastructure/http/resources";
import type { ListParams } from "@/domain/types/api";

export function useZones(params?: ListParams) {
  return useQuery({
    queryKey: ["zones", params],
    queryFn: () => zonesApi.list(params),
  });
}

function invalidateZones(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["zones"] });
  void qc.invalidateQueries({ queryKey: ["perimetres"] });
  void qc.invalidateQueries({ queryKey: ["agents"] });
}

export function useCreateZone() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { nom: string; description?: string }) =>
      zonesApi.create(payload),
    onSuccess: () => invalidateZones(qc),
  });
}

export function useUpdateZone() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: { nom?: string; description?: string | null };
    }) => zonesApi.update(id, payload),
    onSuccess: () => invalidateZones(qc),
  });
}

export function useSyncZoneControleurs() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, agentIds }: { id: string; agentIds: string[] }) =>
      zonesApi.syncControleurs(id, agentIds),
    onSuccess: () => invalidateZones(qc),
  });
}

export function useSyncZoneReleve() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      releveDepuis,
      releveJusque,
      ordreAgentIds,
    }: {
      id: string;
      releveDepuis: string;
      releveJusque: string;
      ordreAgentIds?: string[];
    }) =>
      zonesApi.syncReleve(id, {
        releve_depuis: releveDepuis,
        releve_jusque: releveJusque,
        ordre_agent_ids: ordreAgentIds,
      }),
    onSuccess: () => invalidateZones(qc),
  });
}

export function useDeleteZone() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => zonesApi.destroy(id),
    onSuccess: () => invalidateZones(qc),
  });
}
