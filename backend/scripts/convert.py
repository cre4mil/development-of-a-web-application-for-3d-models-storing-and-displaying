# pyrefly: ignore [missing-import]
import bpy
import sys
import os

def clear_scene():
    # ลบวัตถุทั้งหมดที่มีอยู่ในฉากเริ่มต้นของ Blender (กล้อง, แสง, กล่องลูกบาศก์)
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete(use_global=False)

def convert_model(input_path, output_paths):
    clear_scene()
    
    input_ext = input_path.lower().split('.')[-1]
    
    # 1. นำเข้าไฟล์ (Import) ตามนามสกุลต้นฉบับ
    if input_ext == 'obj':
        bpy.ops.wm.obj_import(filepath=input_path)
    elif input_ext == 'fbx':
        bpy.ops.import_scene.fbx(filepath=input_path)
    elif input_ext in ['gltf', 'glb']:
        bpy.ops.import_scene.gltf(filepath=input_path)
    elif input_ext == 'stl':
        bpy.ops.wm.stl_import(filepath=input_path)
    elif input_ext == 'ply':
        bpy.ops.wm.ply_import(filepath=input_path)
    elif input_ext == 'dae':
        bpy.ops.wm.collada_import(filepath=input_path)
    elif input_ext == '3ds':
        bpy.ops.import_scene.autodesk_3ds(filepath=input_path)
    elif input_ext in ['usdz', 'usd', 'usda', 'usdc']:
        bpy.ops.wm.usd_import(filepath=input_path)
    elif input_ext == 'blend':
        bpy.ops.wm.open_mainfile(filepath=input_path)
    else:
        print(f"Unsupported input format: {input_ext}")
        sys.exit(1)
        
    # 2. ส่งออกไฟล์ (Export) ตามที่ต้องการหลายๆ นามสกุลในรอบเดียว
    for output_path in output_paths:
        output_ext = output_path.lower().split('.')[-1]
        print(f"Exporting to {output_path}...")
        
        try:
            if output_ext == 'obj':
                bpy.ops.wm.obj_export(filepath=output_path)
            elif output_ext == 'fbx':
                bpy.ops.export_scene.fbx(filepath=output_path)
            elif output_ext == 'gltf':
                bpy.ops.export_scene.gltf(filepath=output_path, export_format='GLTF_SEPARATE')
            elif output_ext == 'glb':
                bpy.ops.export_scene.gltf(filepath=output_path, export_format='GLB')
            elif output_ext in ['usdz', 'usd', 'usdc']:
                bpy.ops.wm.usd_export(filepath=output_path)
            else:
                print(f"Unsupported output format: {output_ext}")
        except Exception as e:
            print(f"Failed to export {output_ext}: {e}")

# จุดเริ่มต้นทำงาน: รับค่า arguments จากคำสั่ง command line
if __name__ == "__main__":
    # ตัด arguments ส่วนของ Blender ทิ้งไป เอาแค่ส่วนที่ส่งต่อมาให้ script
    argv = sys.argv
    try:
        idx = argv.index("--") + 1
        argv = argv[idx:] 
    except ValueError:
        print("Usage: blender -b -P convert.py -- <input_file> <output_file1> [output_file2] ...")
        sys.exit(1)
        
    if len(argv) < 2:
        print("Usage: blender -b -P convert.py -- <input_file> <output_file1> [output_file2] ...")
        sys.exit(1)
        
    input_file = argv[0]
    output_files = argv[1:]
    
    print(f"Starting conversion: {input_file}")
    convert_model(input_file, output_files)
    print("All conversions finished!")
