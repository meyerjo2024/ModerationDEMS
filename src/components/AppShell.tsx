import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { GlassHeader } from "./GlassHeader";

/** Authenticated page frame: frosted header + centred content column. */
export async function AppShell({ children, wide }: { children: React.ReactNode; wide?: boolean }) {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  return (
    <>
      <GlassHeader user={{ name: user.name, role: user.role }} />
      <main className={`mx-auto w-full px-4 pb-24 pt-8 sm:px-6 ${wide ? "max-w-7xl" : "max-w-6xl"}`}>{children}</main>
    </>
  );
}
