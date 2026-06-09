import type { CarouselItem } from "@/data/carousel";

type CarouselCardProps = {
  item: CarouselItem;
};

export function CarouselCard({ item }: CarouselCardProps) {
  return (
    <article className="relative h-[360px] w-[82vw] max-w-[680px] shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:h-[420px] sm:w-[620px]">
      <img
        src={item.image}
        alt=""
        className="absolute inset-0 h-full w-full object-cover"
      />
      <div className="absolute inset-0 bg-gradient-to-l from-slate-950/76 via-slate-950/38 to-transparent" />
      <div className="relative flex h-full max-w-[430px] flex-col justify-end p-6 text-white sm:p-8">
        <p className="mb-3 text-sm text-blue-100">{item.tone}</p>
        <h2 className="text-2xl font-bold leading-10 sm:text-4xl sm:leading-[1.6]">
          {item.title}
        </h2>
        <p className="mt-3 text-sm leading-7 text-slate-100 sm:text-base">
          {item.description}
        </p>
      </div>
    </article>
  );
}
