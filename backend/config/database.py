"""
Configures the database connection and provides session management.

This script sets up the SQLAlchemy engine and sessionmaker, defining the connection
to the MySQL database using environment variables. It also provides a base class
for ORM models and a utility function to get a database session.

Key Variables:
- `DB_HOST`: The database host.
- `DB_PORT`: The database port.
- `DB_USER`: The database username.
- `DB_PASSWORD`: The database password.
- `DB_NAME`: The database name.

Inter-script Communication:
- This script is imported by all other Python scripts that need to interact with the database.
"""

import os
from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Database configuration
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_USER = os.getenv('DB_USER', 'radiograb')
DB_PASSWORD = os.getenv('DB_PASSWORD', 'radiograb')
DB_NAME = os.getenv('DB_NAME', 'radiograb')

# Create database URL
DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

# Create engine
engine = create_engine(DATABASE_URL, echo=False)

# Create sessionmaker
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Create base class for models
Base = declarative_base()

def get_db():
    """Get database session"""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

def init_database():
    """Initialize database tables"""
    Base.metadata.create_all(bind=engine)