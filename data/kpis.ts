export type Kpi = {
  id: number;
  title: string;
  weight: number;
};

export const kpis: Kpi[] = [
  { id: 1, title: "دقت در برنامه ریزی توزیع", weight: 30 },
  { id: 2, title: "کیفیت ارتباط با مشتری", weight: 25 },
  { id: 3, title: "سرعت پیگیری امور", weight: 20 },
  { id: 4, title: "نظم در گزارش دهی", weight: 25 },
];
