/* RD500N scroll-driven 3D product page
   Self-contained WebGL2 PBR renderer (no external libraries). */
(() => {
'use strict';
const assetBase = new URL('.', document.currentScript.src);
const pageRoot = document.querySelector('#rex-3d-page') || document.body;
const $ = s => pageRoot.querySelector(s), $$ = s => [...pageRoot.querySelectorAll(s)];
const canvas = $('#gl');
if (!canvas) return;
const gl = canvas.getContext('webgl2', { antialias: true, alpha: true, premultipliedAlpha: true });
const STILL = /[?&]still/.test(location.search);
const reduceMotion = STILL || matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------- math ---------------- */
const sub=(a,b)=>a.map((v,i)=>v-b[i]), dot=(a,b)=>a[0]*b[0]+a[1]*b[1]+a[2]*b[2];
const cross=(a,b)=>[a[1]*b[2]-a[2]*b[1],a[2]*b[0]-a[0]*b[2],a[0]*b[1]-a[1]*b[0]];
const norm=a=>{const l=Math.hypot(...a)||1;return a.map(v=>v/l)};
const lerp=(a,b,t)=>a+(b-a)*t, clamp=(x,a,b)=>Math.min(b,Math.max(a,x));
const smooth=t=>t*t*(3-2*t);
const M={
  mul(a,b){const o=new Float32Array(16);for(let i=0;i<4;i++)for(let j=0;j<4;j++){let s=0;for(let k=0;k<4;k++)s+=a[k*4+j]*b[i*4+k];o[i*4+j]=s;}return o;},
  persp(f,asp,n,fa,sx,sy){const t=1/Math.tan(f/2);return new Float32Array([t/asp,0,0,0,0,t,0,0,sx,sy||0,(fa+n)/(n-fa),-1,0,0,2*fa*n/(n-fa),0]);},
  ortho(l,r,b,t,n,f){return new Float32Array([2/(r-l),0,0,0,0,2/(t-b),0,0,0,0,-2/(f-n),0,-(r+l)/(r-l),-(t+b)/(t-b),-(f+n)/(f-n),1]);},
  look(e,c,u){const z=norm(sub(e,c)),x=norm(cross(u,z)),y=cross(z,x);return new Float32Array([x[0],y[0],z[0],0,x[1],y[1],z[1],0,x[2],y[2],z[2],0,-dot(x,e),-dot(y,e),-dot(z,e),1]);},
  ts(t,s){return new Float32Array([s[0],0,0,0,0,s[1],0,0,0,0,s[2],0,t[0],t[1],t[2],1]);},
  rotX(a){const c=Math.cos(a),s=Math.sin(a);return new Float32Array([1,0,0,0,0,c,s,0,0,-s,c,0,0,0,0,1]);},
  xf(m,p){const x=p[0],y=p[1],z=p[2];const w=m[3]*x+m[7]*y+m[11]*z+m[15];return[(m[0]*x+m[4]*y+m[8]*z+m[12])/w,(m[1]*x+m[5]*y+m[9]*z+m[13])/w,(m[2]*x+m[6]*y+m[10]*z+m[14])/w,w];}
};

/* ---------------- shaders ---------------- */
const VS=`#version 300 es
layout(location=0) in vec3 p; layout(location=1) in vec3 n; layout(location=2) in vec2 uv;
uniform mat4 model,vp,lvp; out vec3 wp,wn; out vec2 tuv; out vec4 lp;
void main(){ vec4 w=model*vec4(p,1.); wp=w.xyz; wn=normalize(mat3(model)*n); tuv=uv;
  lp=lvp*vec4(w.xyz+wn*0.004,1.); gl_Position=vp*w; }`;
const FS=`#version 300 es
precision highp float;
in vec3 wp,wn; in vec2 tuv; in vec4 lp; out vec4 o;
uniform sampler2D baseT,ormT,nT,shadowT; uniform vec4 baseF; uniform int hasBase,isFloor; uniform float nScale,cc,ccr,glow;
uniform vec3 eye,keyDir;
const float PI=3.14159265;
vec3 box(vec3 d,vec3 L,float w0,float r,vec3 I){ float w=sqrt(w0*w0+r*r*1.6); float a=acos(clamp(dot(d,L),-1.,1.)); return I*(w0*w0/(w*w))*exp(-a*a/(2.*w*w)); }
vec3 env(vec3 d,float r){
  vec3 c=mix(vec3(0.16,0.17,0.18),vec3(0.9,0.92,0.95),smoothstep(-0.35,0.9,d.y));
  c+=box(d,normalize(vec3(-0.6,0.55,0.75)),0.30,r,vec3(7.0));
  c+=box(d,normalize(vec3(0.85,0.35,0.2)),0.22,r,vec3(3.4,3.6,3.9));
  c+=box(d,normalize(vec3(0.1,0.95,-0.1)),0.45,r,vec3(3.0));
  c+=box(d,normalize(vec3(-0.2,0.25,-0.95)),0.25,r,vec3(2.6,2.4,2.3));
  float sy=d.y-0.28; float sw=0.05+r*0.6; c+=vec3(2.2)*(0.05/sw)*exp(-sy*sy/(2.*sw*sw))*smoothstep(-0.2,0.6,d.z);
  return c;
}
vec2 envBRDF(float NoV,float r){ const vec4 c0=vec4(-1,-0.0275,-0.572,0.022); const vec4 c1=vec4(1,0.0425,1.04,-0.04); vec4 q=r*c0+c1; float a=min(q.x*q.x,exp2(-9.28*NoV))*q.x+q.y; return vec2(-1.04,1.04)*a+q.zw; }
float shadow(){ vec3 s=lp.xyz/lp.w*0.5+0.5; if(s.x<0.||s.x>1.||s.y<0.||s.y>1.) return 1.; float sh=0.; vec2 ts=1./vec2(textureSize(shadowT,0));
  for(int i=-2;i<=2;i++)for(int j=-2;j<=2;j++){ float d=texture(shadowT,s.xy+vec2(i,j)*ts*1.6).r; sh+= s.z-0.003>d?0.:1.; } return sh/25.; }
vec3 perturb(vec3 N,vec2 uv){
  vec3 m=texture(nT,uv).xyz*2.-1.; m.xy*=nScale;
  vec3 dp1=dFdx(wp),dp2=dFdy(wp); vec2 du1=dFdx(uv),du2=dFdy(uv);
  vec3 dp2p=cross(dp2,N),dp1p=cross(N,dp1); vec3 T=dp2p*du1.x+dp1p*du2.x; vec3 B=dp2p*du1.y+dp1p*du2.y;
  float inv=inversesqrt(max(dot(T,T),dot(B,B))+1e-20); return normalize(mat3(T*inv,B*inv,N)*m); }
vec3 aces(vec3 x){ return clamp((x*(2.51*x+0.03))/(x*(2.43*x+0.59)+0.14),0.,1.); }
void main(){
  if(isFloor==1){ float s=shadow(); float r2=dot(wp.xz,wp.xz); float fade=1.-smoothstep(0.4,1.6,sqrt(r2));
    float a=((1.-s)*0.55+0.35*exp(-r2/0.06))*fade; o=vec4(0.,0.,0.,a); return; }
  vec3 V=normalize(eye-wp); vec3 N=normalize(wn); if(dot(N,V)<0.) N=-N;
  vec3 Ng=N; N=perturb(N,tuv);
  vec3 base=baseF.rgb; if(hasBase==1) base*=pow(texture(baseT,tuv).rgb,vec3(2.2));
  vec3 orm=texture(ormT,tuv).rgb; float rough=clamp(orm.g,0.04,1.); float metal=orm.b;
  vec3 F0=mix(vec3(0.04),base,metal); vec3 diff=base*(1.-metal);
  float NoV=max(dot(N,V),1e-3); vec3 R=reflect(-V,N); float sh=shadow();
  vec2 ab=envBRDF(NoV,rough);
  vec3 spec=env(R,rough)*(F0*ab.x+ab.y);
  vec3 irr=env(N,1.0)*0.55;
  vec3 L=keyDir; vec3 H=normalize(L+V); float NoL=max(dot(N,L),0.), NoH=max(dot(N,H),0.);
  float a=rough*rough; float D=a*a/(PI*pow(NoH*NoH*(a*a-1.)+1.,2.)); float k=a/2.; float G=NoL/(NoL*(1.-k)+k)*NoV/(NoV*(1.-k)+k);
  vec3 Fk=F0+(1.-F0)*pow(1.-max(dot(H,V),0.),5.);
  vec3 direct=(diff/PI*(1.-Fk)+D*G*Fk/max(4.*NoL*NoV,1e-3))*NoL*vec3(2.6)*sh;
  vec3 col=diff*irr*mix(0.55,1.,sh)+spec*mix(0.6,1.,sh)+direct;
  if(cc>0.){ float Fc=0.04+0.96*pow(1.-max(dot(Ng,V),0.),5.); vec3 Rc=reflect(-V,Ng); col=col*(1.-Fc*cc)+env(Rc,ccr)*Fc*cc*mix(0.6,1.,sh);
    float ac=max(ccr*ccr,0.002); float NoHc=max(dot(Ng,H),0.); float Dc=ac*ac/(PI*pow(NoHc*NoHc*(ac*ac-1.)+1.,2.)); col+=cc*Fc*Dc*0.25*max(dot(Ng,L),0.)*vec3(2.6)*sh; }
  col+=glow*vec3(0.9,0.12,0.08)*0.35;
  col=aces(col*0.92); o=vec4(pow(col,vec3(1./2.2)),1.);
}`;
const SVS=`#version 300 es
layout(location=0) in vec3 p; uniform mat4 model,lvp; void main(){gl_Position=lvp*model*vec4(p,1.);}`;
const SFS=`#version 300 es
precision highp float; void main(){}`;
function prog(v,f){const p=gl.createProgram();for(const[t,s]of[[gl.VERTEX_SHADER,v],[gl.FRAGMENT_SHADER,f]]){const sh=gl.createShader(t);gl.shaderSource(sh,s);gl.compileShader(sh);if(!gl.getShaderParameter(sh,gl.COMPILE_STATUS))throw new Error(gl.getShaderInfoLog(sh));gl.attachShader(p,sh);}gl.linkProgram(p);if(!gl.getProgramParameter(p,gl.LINK_STATUS))throw new Error(gl.getProgramInfoLog(p));return p;}

/* ---------------- model loading ---------------- */
async function getGLB(){
  if(location.protocol!=='file:'){
    try{const r=await fetch(new URL('model.glb', assetBase));if(r.ok)return await r.arrayBuffer();}catch(e){}
  }
  // file:// fallback: base64 copy of the model loaded via <script>
  if(!window.RD500N_GLB) await new Promise((res,rej)=>{const s=document.createElement('script');s.src=new URL('model.js', assetBase).href;s.onload=res;s.onerror=rej;document.head.appendChild(s);});
  const b=atob(window.RD500N_GLB);const u=new Uint8Array(b.length);for(let i=0;i<b.length;i++)u[i]=b.charCodeAt(i);window.RD500N_GLB=null;return u.buffer;
}

const draws=[]; let cutterPivot=null; let texs=[];
async function load(){
  const buf=await getGLB(); const dv=new DataView(buf);
  const jl=dv.getUint32(12,true); const J=JSON.parse(new TextDecoder().decode(new Uint8Array(buf,20,jl))); const bo=20+jl+8;
  const bv=i=>{const v=J.bufferViews[i];return new Uint8Array(buf,bo+(v.byteOffset||0),v.byteLength)};
  const aniso=gl.getExtension('EXT_texture_filter_anisotropic');
  texs=await Promise.all(J.images.map(async im=>{
    const bmp=await createImageBitmap(new Blob([bv(im.bufferView)],{type:im.mimeType}));
    const t=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,t);gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,gl.RGBA,gl.UNSIGNED_BYTE,bmp);gl.generateMipmap(gl.TEXTURE_2D);
    gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR_MIPMAP_LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.REPEAT);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.REPEAT);
    if(aniso)gl.texParameterf(gl.TEXTURE_2D,aniso.TEXTURE_MAX_ANISOTROPY_EXT,8);return t;}));
  function node(i,parent,inCutter){
    const n=J.nodes[i]; const local=M.ts(n.translation||[0,0,0],n.scale||[1,1,1]);
    const cut=inCutter||n.name==='CutterWheel';
    if(n.name==='CutterWheel'){cutterPivot=n.translation;}
    const m=n.name==='CutterWheel'?parent:M.mul(parent,local);
    if(n.mesh!==undefined) for(const pr of J.meshes[n.mesh].primitives){
      const vao=gl.createVertexArray();gl.bindVertexArray(vao);
      [['POSITION',0],['NORMAL',1],['TEXCOORD_0',2]].forEach(([k,loc])=>{const a=J.accessors[pr.attributes[k]];const v=J.bufferViews[a.bufferView];
        const b=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,b);gl.bufferData(gl.ARRAY_BUFFER,bv(a.bufferView),gl.STATIC_DRAW);
        gl.enableVertexAttribArray(loc);gl.vertexAttribPointer(loc,a.type==='VEC3'?3:2,a.componentType,!!a.normalized,v.byteStride||0,0);});
      const ia=J.accessors[pr.indices];const ib=gl.createBuffer();gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER,ib);gl.bufferData(gl.ELEMENT_ARRAY_BUFFER,bv(ia.bufferView),gl.STATIC_DRAW);
      draws.push({vao,count:ia.count,type:ia.componentType,local:m,cutter:cut,mat:J.materials[pr.material]});
    }
    (n.children||[]).forEach(c=>node(c,n.name==='CutterWheel'?M.ts([0,0,0],[1,1,1]):m,cut));
  }
  node(0,M.ts([0,0,0],[1,1,1]),false);
  const vao=gl.createVertexArray();gl.bindVertexArray(vao);const b=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,b);const s=2.2;
  gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-s,0,-s,s,0,-s,s,0,s,-s,0,-s,s,0,s,-s,0,s]),gl.STATIC_DRAW);gl.enableVertexAttribArray(0);gl.vertexAttribPointer(0,3,gl.FLOAT,false,0,0);
  gl.vertexAttrib3f(1,0,1,0);gl.vertexAttrib2f(2,0,0);
  draws.push({vao,count:6,floor:true,local:M.ts([0,-0.0005,0],[1,1,1])});
}

/* ---------------- GL setup ---------------- */
let pg,sp,SM=2048,shadowTex,shadowFB,U={};
const keyDir=norm([-0.35,1.0,0.5]);
const lvp=M.mul(M.ortho(-1.3,1.3,-1.3,1.3,0.1,8),M.look(keyDir.map(v=>v*3),[0,0.5,0],[0,1,0]));
function setupGL(){
  pg=prog(VS,FS); sp=prog(SVS,SFS);
  ['vp','lvp','eye','keyDir','model','isFloor','baseF','hasBase','nScale','cc','ccr','glow','shadowT','baseT','ormT','nT'].forEach(n=>U[n]=gl.getUniformLocation(pg,n));
  U.smodel=gl.getUniformLocation(sp,'model');U.slvp=gl.getUniformLocation(sp,'lvp');
  shadowTex=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,shadowTex);gl.texStorage2D(gl.TEXTURE_2D,1,gl.DEPTH_COMPONENT32F,SM,SM);
  gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.NEAREST);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.NEAREST);
  shadowFB=gl.createFramebuffer();gl.bindFramebuffer(gl.FRAMEBUFFER,shadowFB);gl.framebufferTexture2D(gl.FRAMEBUFFER,gl.DEPTH_ATTACHMENT,gl.TEXTURE_2D,shadowTex,0);
  gl.bindFramebuffer(gl.FRAMEBUFFER,null);
}
function resize(){
  const dpr=Math.min(window.devicePixelRatio||1,1.75);
  const w=Math.round(innerWidth*dpr),h=Math.round(innerHeight*dpr);
  if(canvas.width!==w||canvas.height!==h){canvas.width=w;canvas.height=h;dirty=true;}
}

/* ---------------- camera choreography ---------------- */
const sections=$$('[data-cam]');
if (!sections.length) {
  $('#stage').dataset.cam='0,0.60,0.05,-38,11,3.7,-0.30';
  sections.push($('#stage'));
}
const keys=sections.map(s=>{const v=s.dataset.cam.split(',').map(Number);return{t:[v[0],v[1],v[2]],az:v[3],el:v[4],dist:v[5],shift:v[6]}});
const cam={t:[...keys[0].t],az:keys[0].az,el:keys[0].el,dist:keys[0].dist,shift:keys[0].shift};
let activeIdx=0, sectionT=0;
function targetCam(){
  const mid=scrollY+innerHeight*0.5;
  const centers=sections.map(s=>scrollY+s.getBoundingClientRect().top+s.offsetHeight*0.5);
  let i=0; while(i<centers.length-1&&mid>centers[i+1])i++;
  let t=0; if(i<centers.length-1) t=clamp((mid-centers[i])/(centers[i+1]-centers[i]),0,1);
  // hold on each keyframe, then move
  const tt=smooth(clamp((t-0.25)/0.5,0,1));
  const a=keys[i],b=keys[Math.min(i+1,keys.length-1)];
  activeIdx=t>0.5?Math.min(i+1,keys.length-1):i; sectionT=t;
  const out={t:a.t.map((v,k)=>lerp(v,b.t[k],tt)),az:lerp(a.az,b.az,tt),el:lerp(a.el,b.el,tt),dist:lerp(a.dist,b.dist,tt),shift:lerp(a.shift,b.shift,tt)};
  const sec=sections[activeIdx];
  if(sec.dataset.turn&&!reduceMotion) out.az+=Math.sin(now*0.00025)*25;
  if(sec.dataset.hs && focusHS){const h=HS.find(x=>x.id===focusHS);out.t=out.t.map((v,k)=>lerp(v,h.p[k],0.75));out.dist=Math.min(out.dist,h.d||1.4);}
  const mobile=innerWidth<900;
  if(mobile){out.shift=0;out.dist*=activeIdx===0?1.12:1.3;}
  return out;
}

/* ---------------- interaction ---------------- */
let drag=null, dragAz=0, dragEl=0, dirty=true, now=0;
canvas.addEventListener('pointerdown',e=>{drag={x:e.clientX,y:e.clientY,az:dragAz,el:dragEl};canvas.classList.add('drag');canvas.setPointerCapture(e.pointerId);$('#dragTip').style.opacity=0;});
canvas.addEventListener('pointermove',e=>{if(!drag)return;dragAz=drag.az-(e.clientX-drag.x)*0.35;dragEl=clamp(drag.el+(e.clientY-drag.y)*0.2,-10,35);});
const endDrag=()=>{drag=null;canvas.classList.remove('drag');};
canvas.addEventListener('pointerup',endDrag);canvas.addEventListener('pointercancel',endDrag);

let motorOn=false, cutterAngle=0, cutterSpeed=0;
const rpmEl=$('#rpm'),stateEl=$('#state'),led=$('#led');
$('#btnStart')?.addEventListener('click',()=>{motorOn=true;led?.classList.add('on');if(stateEl)stateEl.textContent=stateEl.dataset.running||'RUNNING';});
$('#btnStop')?.addEventListener('click',()=>{motorOn=false;led?.classList.remove('on');if(stateEl)stateEl.textContent=stateEl.dataset.stopped||'STOPPED';});

/* hotspots (model space, metres) */
const HS=[
  {id:'switch',label:'Push-button station',p:[-0.075,1.15,0.09],d:1.1},
  {id:'motor',label:'1 HP motor',p:[-0.09,1.055,-0.14],d:1.3},
  {id:'cutter',label:'HSS cutter head',p:[0.06,0.89,0.10],d:1.0},
  {id:'lever',label:'Hand lever',p:[0.06,0.75,0.155],d:1.1},
  {id:'chute',label:'Stainless chute',p:[0.20,0.45,0.20],d:1.6},
  {id:'base',label:'Base plate',p:[0.08,0.012,0.18],d:1.6},
];
const parts=$$('#steps li,[data-rex-part]');
const hot=$('#hot'); HS.forEach(h=>{
  const part=parts.find(el=>el.dataset.hs===h.id);
  const heading=part?.querySelector('h1,h2,h3,h4,h5,h6,b');
  h.label=heading?.textContent.trim()||(pageRoot.classList.contains('rex-3d-builder')?'':h.label);
  h.el=document.createElement('div');h.el.className='hs';
  const dot=document.createElement('i'),label=document.createElement('span');label.textContent=h.label;
  h.el.append(dot,label);hot.appendChild(h.el);
  if(!h.label)h.el.hidden=true;
});
let focusHS=null;
parts.forEach(li=>{
  const go=()=>{focusHS=focusHS===li.dataset.hs?null:li.dataset.hs;parts.forEach(x=>{x.classList.toggle('on',x.dataset.hs===focusHS);if(x.hasAttribute('aria-pressed'))x.setAttribute('aria-pressed',String(x.dataset.hs===focusHS));});HS.forEach(h=>h.el.classList.toggle('on',h.id===focusHS));};
  li.addEventListener('click',go); li.tabIndex=0; li.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();go();}});
});

/* rail + reveal + counters */
const rail=$('#rail');
sections.forEach((s,i)=>{if(!s.id||!s.dataset.label)return;const a=document.createElement('a');a.href='#'+s.id;a.dataset.section=i;a.textContent=s.dataset.label;a.appendChild(document.createElement('i'));rail.appendChild(a);});
const railLinks=[...rail.children], navLinks=$$('nav a');
const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);
  e.target.querySelectorAll?.('[data-count]').forEach(countUp); if(e.target.dataset?.count)countUp(e.target);}}),{threshold:.2});
$$('.rv').forEach(el=>io.observe(el));
function countUp(el){
  if(el._done||reduceMotion)return; el._done=true;
  const to=+el.dataset.count, from=+(el.dataset.from||0), small=el.querySelector('small'), suffix=small?small.outerHTML:'';
  const range=el.dataset.from!==undefined; const t0=performance.now();
  const step=t=>{const k=smooth(clamp((t-t0)/1400,0,1));const v=Math.round(lerp(range?0:0,to,k));
    el.innerHTML=(range?`${Math.round(lerp(0,from,k))}–${v}`:v)+suffix; if(k<1)requestAnimationFrame(step);};
  requestAnimationFrame(step);
}

/* ---------------- render loop ---------------- */
let last=performance.now(), lastIdx=-1, lastSig='';
function frame(t){
  now=t; const dt=Math.min(0.05,(t-last)/1000); last=t;
  resize();
  const tc=targetCam(); const k=reduceMotion?1:1-Math.exp(-dt*5);
  cam.t=cam.t.map((v,i)=>lerp(v,tc.t[i],k)); cam.az=lerp(cam.az,tc.az,k); cam.el=lerp(cam.el,tc.el,k); cam.dist=lerp(cam.dist,tc.dist,k); cam.shift=lerp(cam.shift,tc.shift,k);
  if(!drag){dragAz*=Math.pow(0.12,dt);dragEl*=Math.pow(0.12,dt);}
  const sec=sections[activeIdx];
  if(activeIdx!==lastIdx){lastIdx=activeIdx;railLinks.forEach(a=>a.classList.toggle('on',Number(a.dataset.section)===activeIdx));
    navLinks.forEach(a=>a.classList.toggle('on',a.getAttribute('href')==='#'+sec.id));
    pageRoot.classList.toggle('show-hs',!!sec.dataset.hs); $('#stage').classList.toggle('dim',sec.id==='contact'); $('#dragTip').style.opacity=activeIdx===0?'':'0'; if(!sec.dataset.hs&&focusHS){focusHS=null;[...parts,...$$('.hs')].forEach(x=>{x.classList.remove('on');if(x.hasAttribute('aria-pressed'))x.setAttribute('aria-pressed','false');});}
    $('#stage').style.setProperty('--gx',cam.shift<-0.05?'68%':cam.shift>0.05?'32%':'50%');}
  // cutter
  const want=motorOn?9:(sec.dataset.spin?2.2:0);
  cutterSpeed=lerp(cutterSpeed,want,1-Math.exp(-dt*(motorOn?1.2:0.9)));
  cutterAngle+=cutterSpeed*dt; if(rpmEl)rpmEl.textContent=Math.round(cutterSpeed/9*100)+'%';
  const sig=[cam.t[0],cam.t[1],cam.t[2],cam.az,cam.el,cam.dist,cam.shift,dragAz,dragEl,cutterAngle,canvas.width,canvas.height,reduceMotion?0:Math.round(now/33)].map(v=>v.toFixed(4)).join();
  if(sig!==lastSig||dirty){lastSig=sig;dirty=false;draw(tc);}
  requestAnimationFrame(frame);
}
function draw(){
  const az=(cam.az+dragAz+(reduceMotion?0:Math.sin(now*0.0003)*3))*Math.PI/180, el=(cam.el+dragEl)*Math.PI/180;
  const dir=[-Math.sin(az)*Math.cos(el),Math.sin(el),Math.cos(az)*Math.cos(el)];
  const eye=cam.t.map((v,i)=>v+dir[i]*cam.dist);
  const asp=canvas.width/canvas.height;
  const fov=asp<1?0.62:0.42;
  const vp=M.mul(M.persp(fov,asp,0.05,20,cam.shift,asp<1?-0.45:0),M.look(eye,cam.t,[0,1,0]));
  const cutM=M.mul(M.ts(cutterPivot,[1,1,1]),M.rotX(cutterAngle));
  for(const d of draws) d.model=d.cutter?M.mul(cutM,d.local):d.local;
  // shadow pass
  gl.bindFramebuffer(gl.FRAMEBUFFER,shadowFB);gl.viewport(0,0,SM,SM);gl.clear(gl.DEPTH_BUFFER_BIT);gl.enable(gl.DEPTH_TEST);
  gl.useProgram(sp);gl.uniformMatrix4fv(U.slvp,false,lvp);
  for(const d of draws){if(d.floor)continue;gl.uniformMatrix4fv(U.smodel,false,d.model);gl.bindVertexArray(d.vao);gl.drawElements(gl.TRIANGLES,d.count,d.type,0);}
  // main pass
  gl.bindFramebuffer(gl.FRAMEBUFFER,null);gl.viewport(0,0,canvas.width,canvas.height);gl.clearColor(0,0,0,0);gl.clear(gl.COLOR_BUFFER_BIT|gl.DEPTH_BUFFER_BIT);
  gl.useProgram(pg);
  gl.uniformMatrix4fv(U.vp,false,vp);gl.uniformMatrix4fv(U.lvp,false,lvp);gl.uniform3fv(U.eye,eye);gl.uniform3fv(U.keyDir,keyDir);
  gl.activeTexture(gl.TEXTURE3);gl.bindTexture(gl.TEXTURE_2D,shadowTex);gl.uniform1i(U.shadowT,3);gl.uniform1i(U.baseT,0);gl.uniform1i(U.ormT,1);gl.uniform1i(U.nT,2);
  const glowOn=0;
  for(const d of draws){
    gl.uniformMatrix4fv(U.model,false,d.model);gl.uniform1i(U.isFloor,d.floor?1:0);
    if(d.floor){gl.enable(gl.BLEND);gl.blendFunc(gl.ONE,gl.ONE_MINUS_SRC_ALPHA);gl.depthMask(false);gl.bindVertexArray(d.vao);gl.drawArrays(gl.TRIANGLES,0,6);gl.depthMask(true);gl.disable(gl.BLEND);continue;}
    const m=d.mat,p=m.pbrMetallicRoughness;
    gl.uniform4fv(U.baseF,p.baseColorFactor);gl.uniform1i(U.hasBase,p.baseColorTexture?1:0);
    const bind=(u,ti)=>{gl.activeTexture(gl.TEXTURE0+u);gl.bindTexture(gl.TEXTURE_2D,texs[ti]);};
    bind(0,(p.baseColorTexture||p.metallicRoughnessTexture).index);bind(1,p.metallicRoughnessTexture.index);bind(2,m.normalTexture.index);
    gl.uniform1f(U.nScale,m.normalTexture.scale);const c=(m.extensions||{}).KHR_materials_clearcoat;
    gl.uniform1f(U.cc,c?c.clearcoatFactor:0);gl.uniform1f(U.ccr,c?c.clearcoatRoughnessFactor:0);gl.uniform1f(U.glow,glowOn);
    gl.bindVertexArray(d.vao);gl.drawElements(gl.TRIANGLES,d.count,d.type,0);
  }
  // hotspots
  if(pageRoot.classList.contains('show-hs')){
    const W=innerWidth,H=innerHeight;
    for(const h of HS){const c=M.xf(vp,h.p);const vis=c[3]>0&&Math.abs(c[0])<1.05&&Math.abs(c[1])<1.05;
      h.el.style.transform=`translate(${(c[0]*0.5+0.5)*W}px,${(0.5-c[1]*0.5)*H}px)`;h.el.style.visibility=vis?'visible':'hidden';}
  }
}

/* ---------------- boot ---------------- */
(async()=>{
  try{
    if(!gl) throw new Error('WebGL2 not available');
    setupGL(); await load(); resize();
    requestAnimationFrame(t=>{last=t;frame(t);});
    setTimeout(()=>$('#loader').classList.add('done'),150);
    document.title=document.title; window.__ready=true;
  }catch(err){
    console.error(err);
    $('#loader').textContent=$('#rex-3d-viewer')?.dataset.errorText||'3D view unavailable on this device.';
    setTimeout(()=>$('#loader').classList.add('done'),2500); window.__ready=true;
  }
})();
})();
