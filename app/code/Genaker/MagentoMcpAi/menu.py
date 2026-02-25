import os
import xml.etree.ElementTree as ET
import re

def extract_menu_items(xml_file):
    
    #print(f"Extracting menu items from {xml_file}")
    tree = ET.parse(xml_file)
    root = tree.getroot()
    
    # Define the namespace if needed
    namespaces = {'xsi': 'http://www.w3.org/2001/XMLSchema-instance'}
    
    menu_items = []
    # Use the correct XPath with namespace if necessary
    for item in root.findall('.//add', namespaces):
        title = item.get('title')
        action = item.get('action')
        if title is not None and action is not None:
            menu_items.append((title, action))
            #print(f"Menu item: {title} - {action}")
    return menu_items

def extract_system_config_items(xml_file):
    tree = ET.parse(xml_file)
    root = tree.getroot()
    config_items = []
    for section in root.findall('.//section'):
        section_label = section.find('label')
        section_id = section.get('id')
        section_description = section.find('comment')
        groups = []
        for group in section.findall('.//group'):
            group_label = group.find('label')
            group_description = group.find('comment')
            fields = []
            for field in group.findall('.//field'):
                field_label = field.find('label')
                field_description = field.find('comment')
                if field_label is not None:
                    fields.append({
                        'label': field_label.text,
                        'description': field_description.text if field_description is not None else ''
                    })
            if group_label is not None:
                groups.append({
                    'label': group_label.text,
                    'description': group_description.text if group_description is not None else '',
                    'fields': fields
                })
        if section_label is not None and section_id is not None:
            config_items.append({
                'label': section_label.text,
                'id': section_id,
                'description': section_description.text if section_description is not None else '',
                'groups': groups
            })
    return config_items

def generate_anchor(text):
    # Convert text to lowercase, replace spaces with hyphens, and remove special characters
    return re.sub(r'[^a-z0-9-]', '', re.sub(r'\s+', '-', text.lower()))

def find_magento_root():
    # Start from the current directory
    current_dir = os.path.dirname(os.path.abspath(__file__))
    while current_dir != os.path.dirname(current_dir):  # Stop at the root directory
        if os.path.exists(os.path.join(current_dir, 'app', 'etc', 'env.php')):
            return current_dir
        current_dir = os.path.dirname(current_dir)
    return None

def generate_menu_md():
    magento_root = find_magento_root()
    if not magento_root:
        print("Magento root directory not found.")
        return

    

    # Print the directory where the script is located
    script_directory = os.path.dirname(os.path.abspath(__file__))
    print(f"Script is located in: {script_directory}")
    
    # Use the current working directory for the output file
    output_file_path = os.path.join(script_directory, 'menu.md')
    print(f"Output file will be created at: {output_file_path}")

    with open(output_file_path, 'w') as md_file:
        md_file.write("# Magento 2 Admin Menu and System Configuration\n\n")

        # Directories to scan
        directories = [
            ##os.path.join(magento_root, 'app', 'code'), 
            os.path.join(magento_root, 'vendor')
        ]

        # Extract admin menu items
        md_file.write("## Admin Menu Items\n")
        for directory in directories:
            for root, dirs, files in os.walk(directory):
                for file in files:
                    if file == 'menu.xml':
                        file_path = os.path.join(root, file)
                        menu_items = extract_menu_items(file_path)
                        for title, action in menu_items:
                            url = f"{{base_url}}/{action.replace('/', '/')}"
                            anchor = generate_anchor(title)
                            md_file.write(f"- [{title}]\n")
                            # Placeholder description, can be customized
                            description = f"Description of {title}"
                            if description:
                                md_file.write(f"  - Description: {description}\n")
                                md_file.write(f"  - URL: {url}\n")

        # Extract system configuration items
        md_file.write("\n## System Configuration Items\n")
        for directory in directories:
            for root, dirs, files in os.walk(directory):
                for file in files:
                    if file == 'system.xml':
                        file_path = os.path.join(root, file)
                        config_items = extract_system_config_items(file_path)
                        for section in config_items:
                            anchor = generate_anchor(section['label'])
                            md_file.write(f"- **[{section['label']}](#{anchor})**\n")
                            if section['description']:
                                md_file.write(f"  - Description: {section['description']}\n")
                            md_file.write(f"  - URL: {{base_url}}/admin/system_config/edit/section/{section['id']}\n")
                            for group in section['groups']:
                                group_anchor = generate_anchor(group['label'])
                                md_file.write(f"  - **[{group['label']}](#{group_anchor})**\n")
                                if group['description']:
                                    md_file.write(f"    - Description: {group['description']}\n")
                                for field in group['fields']:
                                    if field['label'] is not None:
                                        field_anchor = generate_anchor(field['label'])
                                        md_file.write(f"    - [{field['label']}](#{field_anchor}): {field['description']}\n")

if __name__ == "__main__":
    generate_menu_md()