import Link from "next/link";

const navItems = [
  { href: "/dashboard", label: "داشبورد", icon: "▦" },
  { href: "/dashboard/users", label: "کاربران", icon: "◌" },
  { href: "/dashboard/kpis", label: "KPI و شاخص‌ها", icon: "◎" },
  { href: "/dashboard/surveys", label: "نظرسنجی‌ها", icon: "□" },
  { href: "/dashboard/results", label: "نتایج", icon: "▤" },
  { href: "/dashboard/files", label: "فایل‌ها", icon: "▱" },
  { href: "/dashboard/settings", label: "تنظیمات سایت", icon: "⚙" },
];

export function DashboardSidebar() {
  return (
    <aside className="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-72 lg:border-b-0 lg:border-l">
      <div className="flex items-center justify-between px-4 py-4 lg:block lg:px-5">
        <Link href="/" className="text-base font-bold text-slate-950">
          شرکت پخش سبحان
        </Link>
        <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 lg:mt-2 lg:inline-block">
          پنل داخلی
        </span>
      </div>
      <nav className="flex gap-2 overflow-x-auto px-4 pb-4 text-sm lg:block lg:space-y-1 lg:overflow-visible lg:px-5">
        {navItems.map((item, index) => (
          <Link
            key={item.href}
            href={item.href}
            className={`flex shrink-0 items-center gap-3 rounded-xl px-3 py-2 transition ${
              index === 0
                ? "bg-blue-600 text-white"
                : "text-slate-600 hover:bg-slate-100 hover:text-slate-950"
            }`}
          >
            <span className="grid h-7 w-7 place-items-center rounded-lg bg-current/10 text-sm">
              {item.icon}
            </span>
            <span>{item.label}</span>
          </Link>
        ))}
      </nav>
    </aside>
  );
}
