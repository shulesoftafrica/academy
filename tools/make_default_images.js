// Generates the ShuleSoft Academy default pictures (SVG) into assets/frontend/default-new/image/defaults/.
// Run: node tools/make_default_images.js
// The pictures are committed: uploads/ is runtime data (git-ignored), so anything the site must always have lives in assets/.
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, '..', 'assets', 'frontend', 'default-new', 'image', 'defaults');
fs.mkdirSync(path.join(OUT, 'categories'), { recursive: true });
fs.mkdirSync(path.join(OUT, 'courses'), { recursive: true });

// 200x200 white icons (drawn in a local box, placed by the caller)
const icons = {
  cap: `<polygon points="100,38 192,80 100,122 8,80"/><path d="M46 104v38c0 16 108 16 108 0v-38l-54 24z"/><rect x="178" y="82" width="7" height="58" rx="3"/><circle cx="181.5" cy="146" r="9"/>`,
  book: `<path d="M16 46h74c8 0 10 6 10 12v104c-4-8-12-10-20-10H16z"/><path d="M184 46h-74c-8 0-10 6-10 12v104c4-8 12-10 20-10h64z"/><g fill="rgba(0,0,0,.28)"><rect x="32" y="66" width="48" height="7" rx="3"/><rect x="32" y="84" width="48" height="7" rx="3"/><rect x="32" y="102" width="40" height="7" rx="3"/><rect x="120" y="66" width="48" height="7" rx="3"/><rect x="120" y="84" width="48" height="7" rx="3"/><rect x="120" y="102" width="40" height="7" rx="3"/></g>`,
  modules: `<rect x="22" y="22" width="68" height="68" rx="14"/><rect x="110" y="22" width="68" height="68" rx="14" opacity=".75"/><rect x="22" y="110" width="68" height="68" rx="14" opacity=".75"/><rect x="110" y="110" width="68" height="68" rx="14"/>`,
  store: `<path d="M14 36h172l12 44c0 12-10 20-22 20s-20-8-20-18c0 10-8 18-20 18s-20-8-20-18c0 10-8 18-20 18s-20-8-20-18c0 10-8 18-20 18s-20-8-20-18c0 10-8 18-20 18S2 92 2 80z" transform="translate(0 4) scale(.99 .95)"/><rect x="26" y="104" width="148" height="72" rx="6"/><g fill="rgba(0,0,0,.28)"><rect x="42" y="120" width="40" height="56" rx="4"/><rect x="100" y="120" width="56" height="34" rx="4"/></g>`,
  bag: `<path d="M44 70h112l14 104H30z"/><path d="M72 76V58a28 28 0 0 1 56 0v18" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round"/><g fill="rgba(0,0,0,.28)"><circle cx="76" cy="92" r="7"/><circle cx="124" cy="92" r="7"/></g>`,
  calc: `<rect x="34" y="16" width="132" height="168" rx="16"/><rect x="50" y="34" width="100" height="36" rx="8" fill="rgba(0,0,0,.3)"/><g fill="rgba(0,0,0,.28)"><rect x="50" y="88" width="26" height="22" rx="6"/><rect x="87" y="88" width="26" height="22" rx="6"/><rect x="124" y="88" width="26" height="22" rx="6"/><rect x="50" y="122" width="26" height="22" rx="6"/><rect x="87" y="122" width="26" height="22" rx="6"/><rect x="124" y="122" width="26" height="22" rx="6"/><rect x="50" y="154" width="63" height="20" rx="6"/><rect x="124" y="154" width="26" height="20" rx="6"/></g>`,
  school: `<polygon points="100,12 190,62 10,62"/><rect x="22" y="72" width="156" height="14" rx="3"/><g><rect x="34" y="96" width="20" height="64" rx="4"/><rect x="72" y="96" width="20" height="64" rx="4"/><rect x="110" y="96" width="20" height="64" rx="4"/><rect x="148" y="96" width="20" height="64" rx="4"/></g><rect x="14" y="166" width="172" height="16" rx="4"/>`,
  chart: `<rect x="26" y="108" width="34" height="68" rx="6"/><rect x="83" y="70" width="34" height="106" rx="6"/><rect x="140" y="30" width="34" height="146" rx="6"/><rect x="14" y="182" width="172" height="8" rx="4" opacity=".6"/>`,
};

function svg(w, h, c1, c2, icon, opts = {}) {
  const size = opts.iconSize || 200;
  const ix = (w - size) / 2;
  const iy = opts.iconY !== undefined ? opts.iconY : (h - size) / 2 - (opts.title ? 26 : 0);
  const s = size / 200;
  const label = opts.title
    ? `<text x="${w / 2}" y="${h - 98}" text-anchor="middle" font-family="Poppins,Segoe UI,Arial,sans-serif" font-size="34" font-weight="700" fill="#fff">${opts.title[0]}</text>` +
      (opts.title[1] ? `<text x="${w / 2}" y="${h - 58}" text-anchor="middle" font-family="Poppins,Segoe UI,Arial,sans-serif" font-size="34" font-weight="700" fill="#fff">${opts.title[1]}</text>` : '')
    : '';
  const tag = opts.tag ? `<text x="${w / 2}" y="${h - 22}" text-anchor="middle" font-family="Poppins,Segoe UI,Arial,sans-serif" font-size="17" letter-spacing="3" fill="rgba(255,255,255,.75)">SHULESOFT ACADEMY</text>` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" role="img" aria-label="${opts.alt || ''}">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs>
<rect width="${w}" height="${h}" fill="url(#g)"/>
<circle cx="${w * 0.9}" cy="${h * 0.1}" r="${h * 0.42}" fill="#fff" opacity=".07"/>
<circle cx="${w * 0.05}" cy="${h * 0.95}" r="${h * 0.34}" fill="#fff" opacity=".07"/>
<g fill="#fff" transform="translate(${ix} ${iy}) scale(${s})" opacity=".95">${icon}</g>
${label}${tag}
</svg>
`;
}

const teal = ['#14b8a6', '#0b4f60'], blue = ['#0ea5a4', '#1e40af'], indigo = ['#6366f1', '#0f766e'],
  amber = ['#f59e0b', '#c2410c'], rose = ['#fb7185', '#b45309'], slate = ['#14b8a6', '#134e4a'];

const categories = { // square tiles, icon only
  'shulesoft': [teal, 'cap', 'ShuleSoft'],
  'foundation-course': [blue, 'book', 'Foundation Course'],
  'shulesoft-module-training': [indigo, 'modules', 'ShuleSoft Module Training'],
  'lineshop': [amber, 'store', 'Lineshop'],
  'lineshop-training': [rose, 'bag', 'Lineshop Training'],
};
for (const [slug, [cols, icon, alt]] of Object.entries(categories)) {
  fs.writeFileSync(path.join(OUT, 'categories', slug + '.svg'), svg(400, 400, cols[0], cols[1], icons[icon], { iconSize: 190, alt }));
}
fs.writeFileSync(path.join(OUT, 'category.svg'), svg(400, 400, teal[0], teal[1], icons.book, { iconSize: 190, alt: 'Category' }));

const courses = { // 8:5 cards with the course name
  'foundation-shulesoft-certified-user': [blue, 'cap', ['Foundation:', 'ShuleSoft Certified User'], 'Foundation: ShuleSoft Certified User'],
  'mafunzo-kamili-ya-kutumia-lineshop-africa': [amber, 'store', ['Mafunzo kamili ya', 'kutumia Lineshop Africa'], 'Mafunzo kamili ya kutumia Lineshop Africa'],
  'shulesoft-accounts-module-fees-invoices-financial-setup': [teal, 'calc', ['ShuleSoft Accounts Module', 'Fees, Invoices & Finance'], 'ShuleSoft Accounts Module'],
  'lineshop-africa-complete-shop-management-training': [rose, 'bag', ['Lineshop Africa:', 'Shop Management'], 'Lineshop Africa: Complete Shop Management Training'],
  'shulesoft-administration-module-school-setup-people-records': [indigo, 'school', ['ShuleSoft Administration', 'School Setup & Records'], 'ShuleSoft Administration Module'],
};
for (const [slug, [cols, icon, title, alt]] of Object.entries(courses)) {
  const esc = (t) => t.replace(/&/g, '&amp;');
  fs.writeFileSync(path.join(OUT, 'courses', slug + '.svg'), svg(800, 500, cols[0], cols[1], icons[icon], { iconSize: 190, iconY: 70, title: title.map(esc), tag: true, alt }));
}


// people / blog / generic
const avatar = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200" role="img" aria-label="User">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14b8a6"/><stop offset="1" stop-color="#0b4f60"/></linearGradient></defs>
<rect width="200" height="200" fill="url(#g)"/><circle cx="100" cy="78" r="38" fill="#fff" opacity=".92"/><path d="M28 200c0-46 32-74 72-74s72 28 72 74z" fill="#fff" opacity=".92"/></svg>
`;
fs.writeFileSync(path.join(OUT, 'avatar.svg'), avatar);
fs.writeFileSync(path.join(OUT, 'blog.svg'), svg(800, 450, teal[0], teal[1], icons.book, { iconSize: 170, iconY: 70, title: ['ShuleSoft Academy'], tag: false, alt: 'Blog' }));
fs.writeFileSync(path.join(OUT, 'image.svg'), svg(800, 500, teal[0], teal[1], icons.cap, { iconSize: 170, alt: 'Image' }));
console.log('written to', OUT);
