import{_ as o}from"./main.f18430a2.js";import{o as n,e as i,g as l,k as c,r as d,l as m,w as f,j as _,h as $,t as h,s as B}from"./vendor.fd3b1727.js";const k={name:"List"},v={class:"list-none"};function x(e,r,t,s,a,p){return n(),i("div",v,[l(e.$slots,"default")])}var L=o(k,[["render",x]]);const y={name:"ListItem",props:{title:{type:String,required:!1,default:""},active:{type:Boolean,required:!0},index:{type:Number,default:null}},setup(e,{slots:r}){const t="cursor-pointer pb-2 pr-0 text-sm font-medium leading-5  flex items-center";let s=c(()=>!!r.icon),a=c(()=>e.active?`${t} text-primary-500`:`${t} text-gray-500`);return{hasIconSlot:s,containerClass:a}}},g={key:0,class:"mr-3"};function C(e,r,t,s,a,p){const u=d("router-link");return n(),m(u,B(e.$attrs,{class:s.containerClass}),{default:f(()=>[s.hasIconSlot?(n(),i("span",g,[l(e.$slots,"icon")])):_("",!0),$("span",null,h(t.title),1)]),_:3},16,["class"])}var b=o(y,[["render",C]]);export{b as B,L as a};