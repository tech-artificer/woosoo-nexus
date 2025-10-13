import{K as m,d as p,x as C,o as V,u as g,v as z,w as N,l as _}from"./app-DAf1zWRD.js";import{P as j}from"./Primitive-CxYRISpk.js";import{a as B,c as $}from"./utils-CnVfeQtM.js";const k=t=>typeof t=="boolean"?`${t}`:t===0?"0":t,x=B,A=(t,a)=>e=>{var n;if((a==null?void 0:a.variants)==null)return x(t,e==null?void 0:e.class,e==null?void 0:e.className);const{variants:l,defaultVariants:r}=a,b=Object.keys(l).map(s=>{const i=e==null?void 0:e[s],d=r==null?void 0:r[s];if(i===null)return null;const o=k(i)||k(d);return l[s][o]}),u=e&&Object.entries(e).reduce((s,i)=>{let[d,o]=i;return o===void 0||(s[d]=o),s},{}),c=a==null||(n=a.compoundVariants)===null||n===void 0?void 0:n.reduce((s,i)=>{let{class:d,className:o,...y}=i;return Object.entries(y).every(w=>{let[f,h]=w;return Array.isArray(h)?h.includes({...r,...u}[f]):{...r,...u}[f]===h})?[...s,d,o]:s},[]);return x(t,b,c,e==null?void 0:e.class,e==null?void 0:e.className)};/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const O=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var v={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":2,"stroke-linecap":"round","stroke-linejoin":"round"};/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const P=({size:t,strokeWidth:a=2,absoluteStrokeWidth:e,color:n,iconNode:l,name:r,class:b,...u},{slots:c})=>m("svg",{...v,width:t||v.width,height:t||v.height,stroke:n||v.stroke,"stroke-width":e?Number(a)*24/Number(t):a,class:["lucide",`lucide-${O(r??"icon")}`],...u},[...l.map(s=>m(...s)),...c.default?[c.default()]:[]]);/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const T=(t,a)=>(e,{slots:n})=>m(P,{...e,iconNode:a,name:t},n),U=p({__name:"Button",props:{variant:{},size:{},class:{},asChild:{type:Boolean},as:{default:"button"}},setup(t){const a=t;return(e,n)=>(V(),C(g(j),{"data-slot":"button",as:e.as,"as-child":e.asChild,class:z(g($)(g(K)({variant:e.variant,size:e.size}),a.class))},{default:N(()=>[_(e.$slots,"default")]),_:3},8,["as","as-child","class"]))}}),K=A("inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",{variants:{variant:{default:"bg-primary text-primary-foreground shadow-xs hover:bg-primary/90",destructive:"bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",outline:"border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50",secondary:"bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80",ghost:"hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",link:"text-primary underline-offset-4 hover:underline"},size:{default:"h-9 px-4 py-2 has-[>svg]:px-3",sm:"h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",lg:"h-10 rounded-md px-6 has-[>svg]:px-4",icon:"size-9"}},defaultVariants:{variant:"default",size:"default"}});export{U as _,A as a,K as b,T as c};
