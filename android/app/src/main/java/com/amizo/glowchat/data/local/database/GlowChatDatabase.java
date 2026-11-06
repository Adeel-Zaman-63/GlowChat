package com.amizo.glowchat.data.local.database;

import android.content.Context;

import androidx.room.Database;
import androidx.room.Room;
import androidx.room.RoomDatabase;

import com.amizo.glowchat.data.local.dao.ConversationDao;
import com.amizo.glowchat.data.local.entity.ConversationEntity;

@Database(
        entities = {ConversationEntity.class},
        version = 1,
        exportSchema = true
)
public abstract class GlowChatDatabase extends RoomDatabase {

    private static volatile GlowChatDatabase INSTANCE;

    public abstract ConversationDao conversationDao();

    public static GlowChatDatabase getInstance(Context context) {
        if (INSTANCE == null) {
            synchronized (GlowChatDatabase.class) {
                if (INSTANCE == null) {
                    INSTANCE = Room.databaseBuilder(
                            context.getApplicationContext(),
                            GlowChatDatabase.class,
                            "glowchat_local.db"
                    ).build();
                }
            }
        }

        return INSTANCE;
    }
}
