(() => {
  const toJalali = value => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!match) return value || '';
    let gy=+match[1],gm=+match[2],gd=+match[3];
    const gdm=[0,31,59,90,120,151,181,212,243,273,304,334],gy2=gm>2?gy+1:gy;
    let days=355666+365*gy+Math.floor((gy2+3)/4)-Math.floor((gy2+99)/100)+Math.floor((gy2+399)/400)+gd+gdm[gm-1];
    let jy=-1595+33*Math.floor(days/12053);days%=12053;jy+=4*Math.floor(days/1461);days%=1461;
    if(days>365){jy+=Math.floor((days-1)/365);days=(days-1)%365;}
    const jm=days<186?1+Math.floor(days/31):7+Math.floor((days-186)/30),jd=1+(days<186?days%31:(days-186)%30);
    return `${jy}/${String(jm).padStart(2,'0')}/${String(jd).padStart(2,'0')}`;
  };
  document.querySelectorAll('input[type="date"][name="meeting_date"]').forEach(input => {
    const value=input.value||input.getAttribute('value')||'';input.type='text';input.classList.add('jalali-date-input');input.inputMode='numeric';input.value=toJalali(value);
    input.addEventListener('input',()=>{const digits=input.value.replace(/[۰-۹]/g,c=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(c)).replace(/\D/g,'').slice(0,8);input.value=digits.length>6?`${digits.slice(0,4)}/${digits.slice(4,6)}/${digits.slice(6)}`:digits.length>4?`${digits.slice(0,4)}/${digits.slice(4)}`:digits;});
  });
})();
