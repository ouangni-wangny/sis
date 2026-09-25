"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { clientsApi } from "@/infrastructure/http/resources";
import type { ListParams } from "@/domain/types/api";
import type { Client } from "@/domain/types/entities";

export function useClient(id: string | undefined) {
  return useQuery({
    queryKey: ["clients", id],
    queryFn: () => clientsApi.show(id!),
    enabled: Boolean(id),
  });
}

export function useClients(
  params?: ListParams & { type?: string; statut?: string },
) {
  return useQuery({
    queryKey: ["clients", params],
    queryFn: () => clientsApi.list(params),
  });
}

function invalidateClients(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["clients"] });
}

export function useCreateClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Client>) => clientsApi.create(payload),
    onSuccess: () => invalidateClients(qc),
  });
}

export function useUpdateClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<Client> }) =>
      clientsApi.update(id, payload),
    onSuccess: () => invalidateClients(qc),
  });
}

export function useDeleteClient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => clientsApi.destroy(id),
    onSuccess: () => invalidateClients(qc),
  });
}

export function useExportClientsPdf() {
  return useMutation({
    mutationFn: (params?: ListParams & { type?: string; statut?: string }) =>
      clientsApi.exportPdf(params),
  });
}
