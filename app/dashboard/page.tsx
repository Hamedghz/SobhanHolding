import { DashboardHeader } from "@/components/dashboard/DashboardHeader";
import { StatCard } from "@/components/dashboard/StatCard";
import { kpis } from "@/data/kpis";
import { surveyResults, calculateWeightedScore } from "@/data/results";
import { users } from "@/data/users";

export default function DashboardPage() {
  const averageScore = Math.round(
    surveyResults.reduce(
      (sum, result) => sum + calculateWeightedScore(result.scores),
      0,
    ) / surveyResults.length,
  );

  return (
    <>
      <DashboardHeader
        title="داشبورد"
        description="نمایی خلاصه از وضعیت کاربران، شاخص ها و نتایج عملکرد."
      />
      <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
        <StatCard title="تعداد کاربران" value={String(users.length)} helper="مدیران و کاربران فعال سیستم" />
        <StatCard title="تعداد KPIها" value={String(kpis.length)} helper="شاخص های تعریف شده برای ارزیابی" />
        <StatCard title="تعداد نظرسنجی‌ها" value="۳" helper="نظرسنجی های نمونه در چرخه جاری" />
        <StatCard title="میانگین امتیاز عملکرد" value={`${averageScore}%`} helper="محاسبه شده بر اساس وزن شاخص ها" />
      </div>
    </>
  );
}
