"use client";

import { useQuery } from "@tanstack/react-query";
import { entitiesEntitiesShow } from "@/lib/api/entity/entity";
import type { EntityResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "./use-me";

export function useEntity() {
  const me = useMe();
  const entityId = me.data?.entityId ?? null;

  return useQuery({
    queryKey: ["entities", entityId ?? "none"],
    queryFn: async () => {
      if (entityId === null) {
        throw new Error("Current user has no entity");
      }

      return unwrap<EntityResource>(
        await entitiesEntitiesShow(entityId, withAuth())
      );
    },
    enabled: entityId !== null,
    staleTime: 5 * 60 * 1000,
  });
}
