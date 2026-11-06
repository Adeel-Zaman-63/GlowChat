package com.amizo.glowchat.data.local.database;

import android.content.Context;

import androidx.room.Database;
import androidx.room.Room;
import androidx.room.RoomDatabase;
import androidx.room.migration.Migration;
import androidx.sqlite.db.SupportSQLiteDatabase;

import com.amizo.glowchat.data.local.dao.ConversationDao;
import com.amizo.glowchat.data.local.dao.MessageDao;
import com.amizo.glowchat.data.local.entity.ConversationEntity;
import com.amizo.glowchat.data.local.entity.MessageEntity;

@Database(
        entities = {
                ConversationEntity.class,
                MessageEntity.class
        },
        version = 2,
        exportSchema = true
)
public abstract class GlowChatDatabase extends RoomDatabase {

    private static volatile GlowChatDatabase INSTANCE;

    static final Migration MIGRATION_1_2 = new Migration(1, 2) {
        @Override
        public void migrate(SupportSQLiteDatabase database) {

            database.execSQL(
                    "CREATE TABLE IF NOT EXISTS `messages` (" +
                            "`messageId` TEXT NOT NULL, " +
                            "`conversationId` TEXT NOT NULL, " +
                            "`senderUserId` TEXT NOT NULL, " +
                            "`messageType` TEXT NOT NULL, " +
                            "`encryptedPayload` BLOB NOT NULL, " +
                            "`encryptionVersion` INTEGER NOT NULL, " +
                            "`sequenceNumber` INTEGER NOT NULL, " +
                            "`clientCreatedAt` INTEGER NOT NULL, " +
                            "`serverCreatedAt` INTEGER NOT NULL, " +
                            "`localStatus` TEXT NOT NULL, " +
                            "`replyToMessageId` TEXT, " +
                            "`editedAt` INTEGER NOT NULL, " +
                            "`expiresAt` INTEGER NOT NULL, " +
                            "`createdAt` INTEGER NOT NULL, " +
                            "`updatedAt` INTEGER NOT NULL, " +
                            "PRIMARY KEY(`messageId`), " +
                            "FOREIGN KEY(`conversationId`) " +
                            "REFERENCES `conversations`(`conversationId`) " +
                            "ON UPDATE NO ACTION ON DELETE CASCADE)"
            );

            database.execSQL(
                    "CREATE INDEX IF NOT EXISTS " +
                            "`index_messages_conversationId_sequenceNumber` " +
                            "ON `messages` (`conversationId`, `sequenceNumber`)"
            );

            database.execSQL(
                    "CREATE INDEX IF NOT EXISTS " +
                            "`index_messages_conversationId_clientCreatedAt` " +
                            "ON `messages` (`conversationId`, `clientCreatedAt`)"
            );

            database.execSQL(
                    "CREATE INDEX IF NOT EXISTS " +
                            "`index_messages_senderUserId` " +
                            "ON `messages` (`senderUserId`)"
            );
        }
    };

    public abstract ConversationDao conversationDao();

    public abstract MessageDao messageDao();

    public static GlowChatDatabase getInstance(Context context) {
        if (INSTANCE == null) {
            synchronized (GlowChatDatabase.class) {
                if (INSTANCE == null) {
                    INSTANCE = Room.databaseBuilder(
                            context.getApplicationContext(),
                            GlowChatDatabase.class,
                            "glowchat_local.db"
                    )
                            .addMigrations(MIGRATION_1_2)
                            .build();
                }
            }
        }

        return INSTANCE;
    }
}
