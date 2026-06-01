
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forge Panel - Add Item</title>
</head>
<body style="background: #121214; color: #e1e1e6; font-family: monospace; padding: 20px;">

    <div style="max-width: 650px; margin: 30px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">
        
        <h2 style="margin-top: 0; color: #5757df; border-bottom: 1px solid #323238; padding-bottom: 10px;">⚔️ Item Insertion Panel</h2>
        
        <form action="admin_insert_item.php" method="POST">
            
            <h3 style="color: #ffcc00; margin-bottom: 10px;">1. Base Specifications</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display:block; margin-bottom:5px;">Item Name *</label>
                    <input type="text" name="item_name" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px;">Item Type *</label>
                    <select name="item_type" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px;">
                        <option value="armaments">Armaments</option>
                        <option value="helmet">Helmet</option>
                        <option value="armor">Armor</option>
                        <option value="boots">Boots</option>
                        <option value="Accessory">Accessory</option>
                        <option value="Consumable">Consumable</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Item Description</label>
                <textarea name="item_desc" rows="3" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; resize: none; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                <div>
                    <label style="display:block; margin-bottom:5px;">Gold Value Cost (Price)</label>
                    <input type="number" name="price" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px;">Sprite File Name</label>
                    <input type="text" name="sprite" value="default.png" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
            </div>

            <h3 style="color: #ffcc00; margin-bottom: 10px;">2. Item Attribute Modifiers</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 25px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:0.85rem;">Bonus ATK</label>
                    <input type="number" name="att_atk" value="0" style="width:100%; padding:6px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:0.85rem;">Bonus DEF</label>
                    <input type="number" name="att_def" value="0" style="width:100%; padding:6px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:0.85rem;">Bonus SPD</label>
                    <input type="number" name="att_spd" value="0" style="width:100%; padding:6px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:0.85rem;">Heal HP Value</label>
                    <input type="number" name="att_hp" value="0" style="width:100%; padding:6px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
                <div style="grid-column: span 2;">
                    <label style="display:block; margin-bottom:5px; font-size:0.85rem;">Max HP Boost Modifier</label>
                    <input type="number" name="att_max_hp" value="0" style="width:100%; padding:6px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                </div>
            </div>

            <button type="submit" style="background:#5757df; color:white; font-weight:bold; border:none; padding:12px; width:100%; border-radius:4px; cursor:pointer; font-size:1rem; font-family: monospace;">
                Forge Item & Commit Data
            </button>
        </form>
    </div>

</body>
</html>