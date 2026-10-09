import type { AssessmentStatus, Role } from "@prisma/client";

export const STATUS_LABEL: Record<AssessmentStatus, string> = {
  DRAFT: "Draft",
  PENDING_PRE_MODERATION: "Pending Pre-Moderation Review",
  REVISION_REQUESTED: "Revision Requested",
  READY_FOR_POST_MODERATION: "Ready for Post-Moderation",
  PENDING_FINAL_MODERATION: "Pending Final Moderation Review",
  PENDING_EXTERNAL_MODERATION: "Pending External Moderation",
  COMPLETED: "Completed",
};

export const ROLE_LABEL: Record<Role, string> = {
  EXAMINER: "Examiner",
  INTERNAL_MODERATOR: "Internal Moderator",
  EXTERNAL_MODERATOR: "External Moderator",
  HOD: "Head of Department",
};

/** Who must act next, per status. */
export const ACTOR_FOR_STATUS: Record<AssessmentStatus, "examiner" | "internal" | "external" | "none"> = {
  DRAFT: "examiner",
  PENDING_PRE_MODERATION: "internal",
  REVISION_REQUESTED: "examiner",
  READY_FOR_POST_MODERATION: "examiner",
  PENDING_FINAL_MODERATION: "internal",
  PENDING_EXTERNAL_MODERATION: "external",
  COMPLETED: "none",
};

/** The journey shown in the stepper. */
export const GATES = [
  { key: "setup", title: "Pre-Assessment", sub: "Examiner · Section 1" },
  { key: "gate1", title: "Gate 1", sub: "Internal pre-moderation" },
  { key: "harvest", title: "Post-Assessment", sub: "Examiner · Section 2" },
  { key: "gate2", title: "Gate 2", sub: "Internal final review" },
  { key: "gate3", title: "Gate 3", sub: "External (optional)" },
  { key: "done", title: "Archive", sub: "PDF · HOD" },
] as const;

/** Index in GATES of the step currently awaiting action. */
export function currentGateIndex(status: AssessmentStatus, hasExternal: boolean): number {
  switch (status) {
    case "DRAFT":
    case "REVISION_REQUESTED":
      return 0;
    case "PENDING_PRE_MODERATION":
      return 1;
    case "READY_FOR_POST_MODERATION":
      return 2;
    case "PENDING_FINAL_MODERATION":
      return 3;
    case "PENDING_EXTERNAL_MODERATION":
      return hasExternal ? 4 : 3;
    case "COMPLETED":
      return 6;
  }
}

export const AUDIT_LABEL: Record<string, string> = {
  ASSESSMENT_CREATED: "Assessment created",
  SECTION1_SAVED: "Section 1 draft saved",
  FILE_UPLOADED: "File uploaded",
  SECTION1_SUBMITTED: "Section 1 signed & submitted",
  PRE_REVIEW_APPROVED: "Pre-moderation approved",
  PRE_REVIEW_REVISION: "Revision requested",
  SECTION2_SUBMITTED: "Section 2 signed & submitted",
  FINAL_REVIEW_APPROVED: "Final moderation approved",
  FINAL_REVIEW_RETURNED: "Returned to examiner",
  EXTERNAL_REVIEW_APPROVED: "External moderation approved",
  EXTERNAL_REVIEW_RETURNED: "Returned by external moderator",
  REPORT_GENERATED: "Final PDF report generated",
  REPORT_EMAILED: "Report e-mailed to HOD",
  REPORT_EMAIL_FAILED: "Report e-mail failed",
};
