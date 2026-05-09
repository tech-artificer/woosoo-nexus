#!/usr/bin/env python3
"""
Krypton ↔ Woosoo BI Platform: Automated Refresh & Drift Report Generator
Purpose: Execute materialized snapshots, detect drift issues, and publish to BI
Supports: MySQL and PostgreSQL
"""

import argparse
import sys
import json
from datetime import datetime, timedelta
from typing import Dict, List, Tuple
import logging

try:
    import mysql.connector
    from mysql.connector import Error as MySQLError
except ImportError:
    MySQLError = None

try:
    import psycopg2
    from psycopg2 import Error as PostgreSQLError
except ImportError:
    PostgreSQLError = None

import pandas as pd

# ============================================================================
# LOGGING SETUP
# ============================================================================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/app/logs/bi_refresh.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# ============================================================================
# DATABASE ABSTRACTION LAYER
# ============================================================================

class DatabaseConnector:
    """Base class for database connections"""
    def __init__(self, dialect: str, **kwargs):
        self.dialect = dialect
        self.connection = None
        self.connect(**kwargs)

    def connect(self, **kwargs):
        raise NotImplementedError

    def execute_query(self, query: str) -> pd.DataFrame:
        raise NotImplementedError

    def execute_update(self, query: str) -> int:
        raise NotImplementedError

    def close(self):
        if self.connection:
            self.connection.close()
            logger.info(f"Closed {self.dialect} connection")

class MySQLConnector(DatabaseConnector):
    def connect(self, host: str, user: str, password: str, database: str):
        try:
            self.connection = mysql.connector.connect(
                host=host,
                user=user,
                password=password,
                database=database,
                autocommit=True
            )
            logger.info(f"Connected to MySQL: {host}/{database}")
        except MySQLError as e:
            logger.error(f"MySQL connection failed: {e}")
            raise

    def execute_query(self, query: str) -> pd.DataFrame:
        try:
            df = pd.read_sql(query, self.connection)
            logger.debug(f"Query returned {len(df)} rows")
            return df
        except MySQLError as e:
            logger.error(f"MySQL query failed: {e}")
            raise

    def execute_update(self, query: str) -> int:
        try:
            cursor = self.connection.cursor()
            cursor.execute(query)
            rows_affected = cursor.rowcount
            cursor.close()
            logger.debug(f"Update affected {rows_affected} rows")
            return rows_affected
        except MySQLError as e:
            logger.error(f"MySQL update failed: {e}")
            raise

class PostgreSQLConnector(DatabaseConnector):
    def connect(self, host: str, user: str, password: str, database: str):
        try:
            self.connection = psycopg2.connect(
                host=host,
                user=user,
                password=password,
                database=database
            )
            self.connection.autocommit = True
            logger.info(f"Connected to PostgreSQL: {host}/{database}")
        except PostgreSQLError as e:
            logger.error(f"PostgreSQL connection failed: {e}")
            raise

    def execute_query(self, query: str) -> pd.DataFrame:
        try:
            df = pd.read_sql(query, self.connection)
            logger.debug(f"Query returned {len(df)} rows")
            return df
        except PostgreSQLError as e:
            logger.error(f"PostgreSQL query failed: {e}")
            raise

    def execute_update(self, query: str) -> int:
        try:
            cursor = self.connection.cursor()
            cursor.execute(query)
            rows_affected = cursor.rowcount
            self.connection.commit()
            cursor.close()
            logger.debug(f"Update affected {rows_affected} rows")
            return rows_affected
        except PostgreSQLError as e:
            logger.error(f"PostgreSQL update failed: {e}")
            raise

# ============================================================================
# BI PROCESSOR
# ============================================================================

class BIProcessor:
    def __init__(self, db_connector: DatabaseConnector, dialect: str):
        self.db = db_connector
        self.dialect = dialect
        self.report = {
            'timestamp': datetime.now().isoformat(),
            'dialect': dialect,
            'summary': {},
            'drift_issues': [],
            'line_item_issues': [],
            'metrics': {}
        }

    def refresh_materialized_snapshot(self) -> int:
        """Refresh the daily reconciliation snapshot"""
        logger.info("Refreshing materialized snapshot...")
        
        if self.dialect == 'mysql':
            query = "CALL refresh_bi_order_reconciliation_snapshot();"
        else:  # PostgreSQL
            query = "SELECT refresh_bi_order_reconciliation_snapshot();"
        
        rows = self.db.execute_update(query)
        logger.info(f"Materialized snapshot refreshed: {rows} rows")
        return rows

    def get_order_fusion(self, order_id: int = None) -> pd.DataFrame:
        """Fetch order fusion data, optionally filtered by order_id"""
        query = "SELECT * FROM bi_krypton_woosoo_order_fusion"
        if order_id:
            query += f" WHERE krypton_order_id = {order_id}"
        
        df = self.db.execute_query(query)
        logger.info(f"Fetched {len(df)} order fusion records")
        return df

    def get_drift_issues(self, severity: str = None, limit: int = 100) -> pd.DataFrame:
        """Fetch detected drift issues"""
        query = "SELECT * FROM bi_order_drift_detection"
        if severity:
            query += f" WHERE severity = '{severity}'"
        query += f" LIMIT {limit}"
        
        df = self.db.execute_query(query)
        logger.info(f"Fetched {len(df)} drift issues")
        self.report['drift_issues'].append({
            'count': len(df),
            'severity': severity or 'ALL',
            'records': df.to_dict('records')[:10]  # Store first 10 for reporting
        })
        return df

    def get_line_item_issues(self, limit: int = 100) -> pd.DataFrame:
        """Fetch line-item reconciliation issues"""
        query = """
        SELECT * FROM bi_line_item_reconciliation 
        WHERE item_reconciliation_status != 'MATCHED'
        LIMIT {limit}
        """.format(limit=limit)
        
        df = self.db.execute_query(query)
        logger.info(f"Fetched {len(df)} line-item issues")
        self.report['line_item_issues'].append({
            'count': len(df),
            'records': df.to_dict('records')[:10]
        })
        return df

    def get_summary_by_status(self) -> pd.DataFrame:
        """Get order summary by reconciliation status"""
        query = "SELECT * FROM bi_order_summary_by_status"
        df = self.db.execute_query(query)
        logger.info(f"Order summary: {len(df)} status combinations")
        self.report['summary'] = df.to_dict('records')
        return df

    def get_metrics(self) -> Dict:
        """Calculate key metrics for dashboard"""
        metrics = {}
        
        # Total orders
        total_orders = self.db.execute_query(
            "SELECT COUNT(*) AS cnt FROM bi_krypton_woosoo_order_fusion"
        )
        metrics['total_orders'] = int(total_orders.iloc[0]['cnt'])
        
        # Linked vs Unlinked
        status_query = self.db.execute_query("""
            SELECT crosswalk_status, COUNT(*) AS cnt 
            FROM bi_krypton_woosoo_order_fusion 
            GROUP BY crosswalk_status
        """)
        for _, row in status_query.iterrows():
            metrics[f"orders_{row['crosswalk_status'].lower()}"] = int(row['cnt'])
        
        # Amount mismatches
        mismatch_query = self.db.execute_query("""
            SELECT COUNT(*) AS cnt FROM bi_krypton_woosoo_order_fusion 
            WHERE amount_reconciliation = 'AMOUNT_MISMATCH'
        """)
        metrics['amount_mismatches'] = int(mismatch_query.iloc[0]['cnt'])
        
        # High severity drift
        high_severity = self.db.execute_query("""
            SELECT COUNT(*) AS cnt FROM bi_order_drift_detection 
            WHERE severity = 'HIGH'
        """)
        metrics['high_severity_drift'] = int(high_severity.iloc[0]['cnt'])
        
        self.report['metrics'] = metrics
        logger.info(f"Metrics calculated: {metrics}")
        return metrics

    def generate_report(self, output_file: str = 'bi_drift_report.json'):
        """Generate final JSON report"""
        self.get_summary_by_status()
        self.get_drift_issues('HIGH')
        self.get_drift_issues('MEDIUM')
        self.get_line_item_issues()
        self.get_metrics()
        
        with open(output_file, 'w') as f:
            json.dump(self.report, f, indent=2, default=str)
        
        logger.info(f"Report generated: {output_file}")
        return output_file

# ============================================================================
# CLI INTERFACE
# ============================================================================

def main():
    parser = argparse.ArgumentParser(
        description='Krypton ↔ Woosoo BI Platform Refresh & Report Generator'
    )
    
    # Database connection arguments
    parser.add_argument('--dialect', choices=['mysql', 'postgresql'], 
                        default='mysql', help='Database dialect')
    parser.add_argument('--host', default='localhost', help='Database host')
    parser.add_argument('--user', required=True, help='Database user')
    parser.add_argument('--password', required=True, help='Database password')
    parser.add_argument('--database', default='krypton_woosoo', help='Database name')
    
    # Operation arguments
    parser.add_argument('--refresh', action='store_true', 
                        help='Refresh materialized snapshot')
    parser.add_argument('--order-id', type=int, 
                        help='Fetch fusion data for specific order')
    parser.add_argument('--drift', choices=['HIGH', 'MEDIUM', 'LOW'], 
                        help='Get drift issues by severity')
    parser.add_argument('--report', action='store_true', 
                        help='Generate full drift report')
    parser.add_argument('--output', default='bi_drift_report.json', 
                        help='Output file for report')
    
    args = parser.parse_args()
    
    try:
        # Create database connector
        if args.dialect == 'mysql':
            if not MySQLError:
                logger.error("MySQL connector not installed: pip install mysql-connector-python")
                sys.exit(1)
            db = MySQLConnector(
                dialect='mysql',
                host=args.host,
                user=args.user,
                password=args.password,
                database=args.database
            )
        else:
            if not PostgreSQLError:
                logger.error("PostgreSQL connector not installed: pip install psycopg2")
                sys.exit(1)
            db = PostgreSQLConnector(
                dialect='postgresql',
                host=args.host,
                user=args.user,
                password=args.password,
                database=args.database
            )
        
        # Create BI processor
        bi = BIProcessor(db, args.dialect)
        
        # Execute operations
        if args.refresh:
            bi.refresh_materialized_snapshot()
        
        if args.order_id:
            df = bi.get_order_fusion(args.order_id)
            print(df.to_string())
        
        if args.drift:
            df = bi.get_drift_issues(args.drift)
            print(df.to_string())
        
        if args.report:
            bi.generate_report(args.output)
            print(f"Report saved to {args.output}")
        
        db.close()
        logger.info("Completed successfully")
    
    except Exception as e:
        logger.error(f"Fatal error: {e}", exc_info=True)
        sys.exit(1)

if __name__ == '__main__':
    main()

