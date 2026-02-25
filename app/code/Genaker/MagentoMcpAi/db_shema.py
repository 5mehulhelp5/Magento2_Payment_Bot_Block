import pymysql
import json
import argparse

def get_table_structures(pattern):
    config = {
        'host': '127.0.0.1',
        'user': 'magento',
        'password': 'magento',
        'db': 'magento',
        'charset': 'utf8mb4',
        'cursorclass': pymysql.cursors.DictCursor
    }

    connection = None  # Initialize connection outside try block
    try:
        connection = pymysql.connect(**config)
        result = {}

        with connection.cursor() as cursor:
            # Get matching tables
            cursor.execute("""
                SELECT TABLE_NAME 
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND TABLE_NAME LIKE %s
            """, (f'{pattern}%',))
            
            tables = [row['TABLE_NAME'] for row in cursor.fetchall()]

            for table in tables:
                cursor.execute(f"SHOW CREATE TABLE `{table}`")
                create_table = cursor.fetchone()['Create Table']
                
                # Clean up formatting
                formatted = create_table.replace('\\n', '\n').replace('\\t', '  ')
                result[table] = formatted.replace('\\', '\\\\').replace('"', '\\"')

        return json.dumps(result, indent=2)

    except pymysql.Error as e:
        print(f"Database error: {e}")
        return None
    finally:
        # Proper connection cleanup
        if connection and connection.open:
            try:
                connection.close()
                print("Database connection closed")
            except Exception as e:
                print(f"Error closing connection: {e}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Export MySQL table structures')
    parser.add_argument('-p', '--pattern', required=True, 
                       help='Table name pattern (e.g., catalog)')
    parser.add_argument('-o', '--output', default='tables.json',
                       help='Output JSON filename')
    
    args = parser.parse_args()
    
    output = get_table_structures(args.pattern)
    if output:
        with open(args.output, 'w') as f:
            f.write(output)
        print(f"Exported {args.output} successfully")
