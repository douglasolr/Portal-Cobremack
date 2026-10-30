import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
export default defineConfig({define:{'process.env.NODE_ENV':JSON.stringify('production')},plugins:[tailwindcss()],resolve:{alias:{'@':path.resolve(import.meta.dirname,'src')}},build:{rollupOptions:{onwarn(warning,warn){if(warning.code==='MODULE_LEVEL_DIRECTIVE' && warning.message.includes('use client'))return;warn(warning);}},outDir:'../assets/editor-dist',emptyOutDir:true,lib:{entry:'src/main.tsx',name:'PWLEnquetesEditor',formats:['iife'],fileName:()=> 'editor.js'},cssCodeSplit:false}});
