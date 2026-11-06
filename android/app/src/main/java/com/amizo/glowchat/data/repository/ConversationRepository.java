package com.amizo.glowchat.data.repository;

import android.content.Context;

import androidx.lifecycle.LiveData;

import com.amizo.glowchat.data.local.dao.ConversationDao;
import com.amizo.glowchat.data.local.database.GlowChatDatabase;
import com.amizo.glowchat.data.local.entity.ConversationEntity;

import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class ConversationRepository {

    private final ConversationDao conversationDao;

    private final ExecutorService databaseExecutor =
            Executors.newSingleThreadExecutor();

    public ConversationRepository(Context context) {
        GlowChatDatabase database =
                GlowChatDatabase.getInstance(context);

        conversationDao = database.conversationDao();
    }

    public LiveData<List<ConversationEntity>> observeAllConversations() {
        return conversationDao.observeAllConversations();
    }

    public LiveData<List<ConversationEntity>> observeUnreadConversations() {
        return conversationDao.observeUnreadConversations();
    }

    public LiveData<List<ConversationEntity>> observeFavoriteConversations() {
        return conversationDao.observeFavoriteConversations();
    }

    public LiveData<List<ConversationEntity>> observeGroupConversations() {
        return conversationDao.observeGroupConversations();
    }

    public LiveData<List<ConversationEntity>> observeArchivedConversations() {
        return conversationDao.observeArchivedConversations();
    }

    public LiveData<ConversationEntity> observeConversation(String conversationId) {
        return conversationDao.observeConversation(conversationId);
    }

    public void saveConversation(ConversationEntity conversation) {
        databaseExecutor.execute(
                () -> conversationDao.insertOrUpdate(conversation)
        );
    }

    public void saveConversations(List<ConversationEntity> conversations) {
        databaseExecutor.execute(
                () -> conversationDao.insertOrUpdateAll(conversations)
        );
    }

    public void deleteConversation(String conversationId) {
        databaseExecutor.execute(
                () -> conversationDao.deleteConversation(conversationId)
        );
    }

    public void deleteAllConversations() {
        databaseExecutor.execute(
                conversationDao::deleteAllConversations
        );
    }
}
