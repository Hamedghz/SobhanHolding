export type UserFile = {
  id: number;
  name: string;
  size: string;
  uploadedAt: string;
};

export const files: UserFile[] = [
  {
    id: 1,
    name: "گزارش عملکرد خرداد.pdf",
    size: "۲.۴ مگابایت",
    uploadedAt: "۱۴۰۵/۰۳/۱۰",
  },
  {
    id: 2,
    name: "برنامه توزیع هفتگی.xlsx",
    size: "۸۶۰ کیلوبایت",
    uploadedAt: "۱۴۰۵/۰۳/۱۲",
  },
  {
    id: 3,
    name: "مستندات نظرسنجی.docx",
    size: "۱.۱ مگابایت",
    uploadedAt: "۱۴۰۵/۰۳/۱۵",
  },
];
