import Link from "next/link";

const navItems = [
  { href: "/", label: "خانه" },
  { href: "/login", label: "ورود" },
  { href: "/dashboard", label: "داشبورد" },
  { href: "#contact", label: "تماس" },
];

export function Header() {
  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
        <Link href="/" className="text-base font-bold text-slate-950">
          شرکت پخش سبحان
        </Link>
        <nav className="flex items-center gap-1 text-sm text-slate-600">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="rounded-xl px-3 py-2 transition hover:bg-slate-100 hover:text-slate-950"
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </div>
    </header>
  );
}
