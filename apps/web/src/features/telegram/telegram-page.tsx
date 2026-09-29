"use client";

import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import {
  AlertCircle,
  Check,
  Clock,
  MessageSquare,
  MoreHorizontal,
  Pencil,
  Plus,
  Send,
  Trash2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import {
  telegramContactsDestroy,
  telegramContactsIndex,
} from "@/lib/api/telegram-contact/telegram-contact";
import {
  telegramMessagesIndex,
  telegramMessagesStore,
} from "@/lib/api/telegram-message/telegram-message";
import {
  ContactDialog,
  type TelegramContact,
} from "./components/contact-dialog";

type TelegramMessage = {
  id: string;
  direction: "in" | "out";
  telegramContactId: string | null;
  telegramChatId: number | null;
  telegramMessageId: number | null;
  body: string | null;
  status: "queued" | "sent" | "failed" | "received" | "needs_review";
  error: string | null;
  createdAt: string;
};

function matchesSearch(contact: TelegramContact, search: string): boolean {
  const needle = search.trim().toLowerCase();
  if (!needle) return true;
  return [contact.name, contact.phone, contact.username]
    .filter(Boolean)
    .some((value) => (value as string).toLowerCase().includes(needle));
}

function MessageStatus({ status, error }: { status: TelegramMessage["status"]; error: string | null }) {
  const t = useTranslations("telegram.chat");
  if (status === "failed") {
    return (
      <span className="inline-flex items-center gap-1 text-destructive" title={error ?? t("failed")}>
        <AlertCircle className="size-3" />
        {t("failed")}
      </span>
    );
  }
  if (status === "queued") {
    return (
      <span className="inline-flex items-center gap-1 text-muted-foreground">
        <Clock className="size-3" />
        {t("queued")}
      </span>
    );
  }
  if (status === "sent") {
    return (
      <span className="inline-flex items-center gap-1">
        <Check className="size-3" />
        {t("sent")}
      </span>
    );
  }
  if (status === "needs_review") {
    return <span className="text-muted-foreground">{t("needsReview")}</span>;
  }
  return null;
}

export function TelegramPage() {
  const t = useTranslations("telegram.contacts");
  const tc = useTranslations("telegram.chat");
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("telegram.manage");

  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [editing, setEditing] = useState<TelegramContact | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<TelegramContact | null>(null);
  const [draft, setDraft] = useState("");
  const bottomRef = useRef<HTMLDivElement | null>(null);

  const contactsQuery = useQuery({
    queryKey: ["telegramContacts"],
    queryFn: async () =>
      unwrap<TelegramContact[]>(await telegramContactsIndex(withAuth())),
  });

  const contacts = contactsQuery.data ?? [];
  const selected = contacts.find((contact) => contact.id === selectedId) ?? null;
  // Derived so a contact removed server-side drops out without effect-sync.
  const activeId = selected?.id ?? null;

  const messagesQuery = useQuery({
    queryKey: ["telegramMessages", activeId],
    enabled: Boolean(activeId),
    queryFn: async () =>
      unwrap<TelegramMessage[]>(
        await telegramMessagesIndex(
          { telegram_contact_id: activeId as string, per_page: 100 },
          withAuth(),
        ),
      ),
    refetchInterval: 5000,
  });

  // The API returns newest-first; the conversation reads chronologically.
  const messages = [...(messagesQuery.data ?? [])].reverse();

  const sendMutation = useMutation({
    mutationFn: async () => {
      const body = draft.trim();
      unwrap(
        await telegramMessagesStore(
          { telegram_contact_id: activeId as string, body },
          withAuth(),
        ),
      );
    },
    onSuccess: () => {
      setDraft("");
      qc.invalidateQueries({ queryKey: ["telegramMessages", activeId] });
      qc.invalidateQueries({ queryKey: ["telegramContacts"] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await telegramContactsDestroy(id, withAuth())),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ["telegramContacts"] });
      qc.invalidateQueries({ queryKey: ["telegramMessages"] });
      if (selectedId === id) setSelectedId(null);
      setDeleteTarget(null);
      toast.success(t("deleted"));
    },
  });

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages.length, activeId]);

  const filteredContacts = contacts.filter((contact) =>
    matchesSearch(contact, search),
  );

  return (
    <div className="flex h-[calc(100dvh-13rem)] min-h-[420px] overflow-hidden rounded-xl border">
      {/* Contact list */}
      <div className="flex w-80 shrink-0 flex-col border-r">
        <div className="space-y-3 border-b p-3">
          <div className="flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold">{t("title")}</h2>
            {canManage ? (
              <Button
                size="sm"
                onClick={() => {
                  setEditing(null);
                  setFormOpen(true);
                }}
              >
                <Plus className="mr-1 size-4" />
                {t("new")}
              </Button>
            ) : null}
          </div>
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
          />
        </div>
        <div className="flex-1 overflow-y-auto p-2">
          {contactsQuery.isPending ? (
            <div className="space-y-2 p-1">
              {Array.from({ length: 5 }).map((_, index) => (
                <Skeleton key={index} className="h-12 w-full" />
              ))}
            </div>
          ) : filteredContacts.length === 0 ? (
            <p className="p-3 text-sm text-muted-foreground">
              {contacts.length === 0 ? t("empty") : t("noMatches")}
            </p>
          ) : (
            <ul className="space-y-1">
              {filteredContacts.map((contact) => {
                const active = contact.id === selectedId;
                const handle = contact.username ?? contact.phone ?? "—";
                return (
                  <li key={contact.id}>
                    <div
                      className={`group flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left ${
                        active ? "bg-accent" : "hover:bg-accent/50"
                      }`}
                    >
                      <button
                        type="button"
                        className="min-w-0 flex-1"
                        onClick={() => setSelectedId(contact.id)}
                      >
                        <span className="block truncate text-sm font-medium">
                          {contact.name}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {handle}
                        </span>
                      </button>
                      {canManage ? (
                        <DropdownMenu>
                          <DropdownMenuTrigger
                            render={
                              <Button
                                variant="ghost"
                                className="size-7 shrink-0 p-0 opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100"
                              />
                            }
                          >
                            <span className="sr-only">{t("openMenu")}</span>
                            <MoreHorizontal className="size-4" />
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                              <DropdownMenuItem
                                onClick={() => {
                                  setEditing(contact);
                                  setFormOpen(true);
                                }}
                              >
                                <Pencil className="mr-2 size-4" />
                                {t("edit")}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-destructive"
                                onClick={() => setDeleteTarget(contact)}
                              >
                                <Trash2 className="mr-2 size-4" />
                                {t("delete")}
                              </DropdownMenuItem>
                            </DropdownMenuGroup>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      ) : null}
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>

      {/* Conversation */}
      <div className="flex min-w-0 flex-1 flex-col">
        {!selected ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-2 text-muted-foreground">
            <MessageSquare className="size-8" />
            <p className="text-sm">{tc("selectPrompt")}</p>
          </div>
        ) : (
          <>
            <div className="flex items-center gap-3 border-b px-4 py-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold">
                  {selected.name}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                  {selected.username ?? selected.phone ?? "—"}
                </p>
              </div>
            </div>

            <div className="flex-1 space-y-3 overflow-y-auto p-4">
              {messagesQuery.isPending ? (
                <div className="space-y-3">
                  {Array.from({ length: 3 }).map((_, index) => (
                    <Skeleton key={index} className="h-12 w-2/3" />
                  ))}
                </div>
              ) : messages.length === 0 ? (
                <p className="text-center text-sm text-muted-foreground">
                  {tc("noMessages")}
                </p>
              ) : (
                messages.map((message) => (
                  <div
                    key={message.id}
                    className={`flex max-w-full flex-col ${
                      message.direction === "out" ? "items-end" : "items-start"
                    }`}
                  >
                    <div
                      className={`max-w-[75%] rounded-xl px-3 py-2 text-sm whitespace-pre-wrap break-words ${
                        message.direction === "out"
                          ? "bg-primary text-primary-foreground"
                          : "bg-muted"
                      }`}
                    >
                      {message.body}
                    </div>
                    <span
                      className={`mt-1 flex items-center gap-2 text-[11px] ${
                        message.direction === "out"
                          ? "text-primary-foreground/70"
                          : "text-muted-foreground"
                      }`}
                    >
                      {new Date(message.createdAt).toLocaleTimeString([], {
                        hour: "2-digit",
                        minute: "2-digit",
                      })}
                      {message.direction === "out" ? (
                        <MessageStatus
                          status={message.status}
                          error={message.error}
                        />
                      ) : null}
                    </span>
                  </div>
                ))
              )}
              <div ref={bottomRef} />
            </div>

            <form
              className="flex items-end gap-2 border-t p-3"
              onSubmit={(e) => {
                e.preventDefault();
                if (!draft.trim() || sendMutation.isPending) return;
                sendMutation.mutate();
              }}
            >
              <Textarea
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                placeholder={tc("placeholder")}
                rows={1}
                className="max-h-32 min-h-10 flex-1 resize-none"
                onKeyDown={(e) => {
                  if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    if (draft.trim() && !sendMutation.isPending) {
                      sendMutation.mutate();
                    }
                  }
                }}
              />
              <Button
                type="submit"
                disabled={!draft.trim() || sendMutation.isPending}
              >
                <Send className="mr-2 size-4" />
                {sendMutation.isPending ? tc("sending") : tc("send")}
              </Button>
            </form>
          </>
        )}
      </div>

      {formOpen && (
        <ContactDialog
          key={editing?.id ?? "new"}
          open
          onOpenChange={(open) => {
            if (!open) {
              setFormOpen(false);
              setEditing(null);
            }
          }}
          editing={editing}
          onSaved={(id) => {
            if (!selectedId) setSelectedId(id);
          }}
        />
      )}

      <Dialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {t.rich("deleteDescription", {
                name: deleteTarget?.name ?? "",
                strong: (chunks) => <strong>{chunks}</strong>,
              })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              {t("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() =>
                deleteTarget && deleteMutation.mutate(deleteTarget.id)
              }
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? t("deleting") : t("confirmDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
