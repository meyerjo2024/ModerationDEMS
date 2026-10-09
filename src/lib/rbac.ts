import type { Assessment, AssessmentStatus, Subject } from "@prisma/client";
import type { SessionUser } from "./auth";

type A = Pick<Assessment, "examinerId" | "internalModeratorId" | "externalModeratorId" | "status"> & {
  subject: Pick<Subject, "hodId">;
};

export function canView(user: SessionUser, a: A): boolean {
  if (a.examinerId === user.id || a.internalModeratorId === user.id) return true;
  if (a.subject.hodId === user.id) return true;
  // External moderators only see records once they are handed to them.
  if (a.externalModeratorId === user.id) return a.status === "PENDING_EXTERNAL_MODERATION" || a.status === "COMPLETED";
  return false;
}

const EXAMINER_STATES: AssessmentStatus[] = ["DRAFT", "REVISION_REQUESTED", "READY_FOR_POST_MODERATION"];

export function needsMyAction(user: SessionUser, a: A): boolean {
  if (a.examinerId === user.id && EXAMINER_STATES.includes(a.status)) return true;
  if (a.internalModeratorId === user.id && (a.status === "PENDING_PRE_MODERATION" || a.status === "PENDING_FINAL_MODERATION")) return true;
  if (a.externalModeratorId === user.id && a.status === "PENDING_EXTERNAL_MODERATION") return true;
  return false;
}
