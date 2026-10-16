function u(t){if(t<1024)return`${t} B`;const r=["KB","MB","GB","TB"];let e=t/1024,n=0;for(;e>=1024&&n<r.length-1;)e/=1024,n++;return`${Number(e.toFixed(1))} ${r[n]}`}export{u as f};
