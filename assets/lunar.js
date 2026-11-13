/* Lịch âm Việt Nam (múi giờ UTC+7) – thuật toán Hồ Ngọc Đức */
(function (root) {
  const PI = Math.PI, TZ = 7;
  const CAN = ['Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu', 'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý'];
  const CHI = ['Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ', 'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi'];

  function jdFromDate(dd, mm, yy) {
    const a = Math.floor((14 - mm) / 12), y = yy + 4800 - a, m = mm + 12 * a - 3;
    let jd = dd + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;
    if (jd < 2299161) jd = dd + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - 32083;
    return jd;
  }
  function newMoon(k) {
    const T = k / 1236.85, T2 = T * T, T3 = T2 * T, dr = PI / 180;
    let Jd1 = 2415020.75933 + 29.53058868 * k + 0.0001178 * T2 - 0.000000155 * T3;
    Jd1 += 0.00033 * Math.sin((166.56 + 132.87 * T - 0.009173 * T2) * dr);
    const M = 359.2242 + 29.10535608 * k - 0.0000333 * T2 - 0.00000347 * T3;
    const Mpr = 306.0253 + 385.81691806 * k + 0.0107306 * T2 + 0.00001236 * T3;
    const F = 21.2964 + 390.67050646 * k - 0.0016528 * T2 - 0.00000239 * T3;
    let C1 = (0.1734 - 0.000393 * T) * Math.sin(M * dr) + 0.0021 * Math.sin(2 * dr * M);
    C1 -= 0.4068 * Math.sin(Mpr * dr) - 0.0161 * Math.sin(dr * 2 * Mpr);
    C1 -= 0.0004 * Math.sin(dr * 3 * Mpr);
    C1 += 0.0104 * Math.sin(dr * 2 * F) - 0.0051 * Math.sin(dr * (M + Mpr));
    C1 -= 0.0074 * Math.sin(dr * (M - Mpr)) - 0.0004 * Math.sin(dr * (2 * F + M));
    C1 -= 0.0004 * Math.sin(dr * (2 * F - M)) + 0.0006 * Math.sin(dr * (2 * F + Mpr));
    C1 += 0.0010 * Math.sin(dr * (2 * F - Mpr)) + 0.0005 * Math.sin(dr * (2 * Mpr + M));
    const dt = T < -11
      ? 0.001 + 0.000839 * T + 0.0002261 * T2 - 0.00000845 * T3 - 0.000000081 * T * T3
      : -0.000278 + 0.000265 * T + 0.000262 * T2;
    return Jd1 + C1 - dt;
  }
  function sunLongitude(jdn) {
    const T = (jdn - 2451545.0) / 36525, T2 = T * T, dr = PI / 180;
    const M = 357.52910 + 35999.05030 * T - 0.0001559 * T2 - 0.00000048 * T * T2;
    const L0 = 280.46645 + 36000.76983 * T + 0.0003032 * T2;
    let DL = (1.914600 - 0.004817 * T - 0.000014 * T2) * Math.sin(dr * M);
    DL += (0.019993 - 0.000101 * T) * Math.sin(dr * 2 * M) + 0.000290 * Math.sin(dr * 3 * M);
    let L = (L0 + DL) * dr;
    L -= PI * 2 * Math.floor(L / (PI * 2));
    return L;
  }
  const sunLong = (dayNumber) => Math.floor(sunLongitude(dayNumber - 0.5 - TZ / 24) / PI * 6);
  const newMoonDay = (k) => Math.floor(newMoon(k) + 0.5 + TZ / 24);
  function lunarMonth11(yy) {
    const off = jdFromDate(31, 12, yy) - 2415021;
    const k = Math.floor(off / 29.530588853);
    let nm = newMoonDay(k);
    if (sunLong(nm) >= 9) nm = newMoonDay(k - 1);
    return nm;
  }
  function leapMonthOffset(a11) {
    const k = Math.floor((a11 - 2415021.076998695) / 29.530588853 + 0.5);
    let last = 0, i = 1, arc = sunLong(newMoonDay(k + i));
    do { last = arc; i++; arc = sunLong(newMoonDay(k + i)); } while (arc !== last && i < 14);
    return i - 1;
  }
  const cache = new Map();
  function solarToLunar(dd, mm, yy) {
    const key = yy * 10000 + mm * 100 + dd;
    if (cache.has(key)) return cache.get(key);
    const dayNumber = jdFromDate(dd, mm, yy);
    const k = Math.floor((dayNumber - 2415021.076998695) / 29.530588853);
    let monthStart = newMoonDay(k + 1);
    if (monthStart > dayNumber) monthStart = newMoonDay(k);
    let a11 = lunarMonth11(yy), b11 = a11, lunarYear;
    if (a11 >= monthStart) { lunarYear = yy; a11 = lunarMonth11(yy - 1); }
    else { lunarYear = yy + 1; b11 = lunarMonth11(yy + 1); }
    const day = dayNumber - monthStart + 1;
    const diff = Math.floor((monthStart - a11) / 29);
    let leap = 0, month = diff + 11;
    if (b11 - a11 > 365) {
      const lm = leapMonthOffset(a11);
      if (diff >= lm) { month = diff + 10; if (diff === lm) leap = 1; }
    }
    if (month > 12) month -= 12;
    if (month >= 11 && diff < 4) lunarYear -= 1;
    const res = { d: day, m: month, y: lunarYear, leap, jd: dayNumber };
    cache.set(key, res);
    return res;
  }
  const yearName = (y) => CAN[(y + 6) % 10] + ' ' + CHI[(y + 8) % 12];
  const dayName = (jd) => CAN[(jd + 9) % 10] + ' ' + CHI[(jd + 1) % 12];

  const LUNAR_HOLIDAYS = {
    '1/1': 'Tết Nguyên Đán', '2/1': 'Mùng 2 Tết', '3/1': 'Mùng 3 Tết', '15/1': 'Rằm Thượng Nguyên', '10/3': 'Giỗ Tổ Hùng Vương',
    '15/4': 'Phật Đản', '5/5': 'Tết Đoan Ngọ', '15/7': 'Vu Lan', '15/8': 'Tết Trung Thu', '23/12': 'Ông Táo chầu trời'
  };
  const SOLAR_HOLIDAYS = {
    '1/1': 'Tết Dương lịch', '14/2': 'Valentine', '8/3': 'Quốc tế Phụ nữ', '30/4': 'Giải phóng miền Nam', '1/5': 'Quốc tế Lao động',
    '1/6': 'Quốc tế Thiếu nhi', '2/9': 'Quốc khánh', '20/10': 'Phụ nữ Việt Nam', '20/11': 'Nhà giáo Việt Nam', '24/12': 'Đêm Giáng sinh', '25/12': 'Giáng sinh'
  };
  function holiday(date) {
    const l = solarToLunar(date.getDate(), date.getMonth() + 1, date.getFullYear());
    const lh = !l.leap && LUNAR_HOLIDAYS[l.d + '/' + l.m];
    const sh = SOLAR_HOLIDAYS[date.getDate() + '/' + (date.getMonth() + 1)];
    return lh || sh || '';
  }
  function label(date) {
    const l = solarToLunar(date.getDate(), date.getMonth() + 1, date.getFullYear());
    return l.d === 1 || (l.d === 1) ? l.d + '/' + l.m + (l.leap ? 'n' : '') : String(l.d);
  }
  function full(date) {
    const l = solarToLunar(date.getDate(), date.getMonth() + 1, date.getFullYear());
    return 'Ngày ' + l.d + ' tháng ' + l.m + (l.leap ? ' nhuận' : '') + ' năm ' + yearName(l.y) + ' · ngày ' + dayName(l.jd);
  }

  root.Lunar = { solarToLunar, yearName, dayName, holiday, label, full };
  if (typeof module !== 'undefined') module.exports = root.Lunar;
})(typeof window !== 'undefined' ? window : globalThis);
