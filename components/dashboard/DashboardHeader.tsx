type DashboardHeaderProps = {
  title: string;
  description?: string;
};

export function DashboardHeader({ title, description }: DashboardHeaderProps) {
  return (
    <header className="flex flex-col gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <div>
        <h1 className="text-xl font-bold text-slate-950">{title}</h1>
        {description ? (
          <p className="mt-1 text-sm leading-6 text-slate-500">{description}</p>
        ) : null}
      </div>
      <div className="flex items-center gap-3">
        <button className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
          خروج
        </button>
        <div className="text-left">
          <p className="text-sm font-semibold text-slate-950">علی احمدی</p>
          <p className="text-xs text-slate-500">مدیر سیستم</p>
        </div>
        <div className="grid h-10 w-10 place-items-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
          ع
        </div>
      </div>
    </header>
  );
}
