import { DashboardSidebar } from "@/components/dashboard/DashboardSidebar";

export default function DashboardLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <main className="min-h-screen bg-slate-50 lg:flex">
      <DashboardSidebar />
      <div className="min-w-0 flex-1">{children}</div>
    </main>
  );
}
